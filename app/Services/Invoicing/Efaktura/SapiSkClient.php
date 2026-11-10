<?php

namespace App\Services\Invoicing\Efaktura;

use App\Models\EfakturaCpdsProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SapiSkClient
{
    /**
     * @return array<string, mixed>
     */
    public function authenticate(string $clientId, string $clientSecret, ?string $baseUrl = null): array
    {
        $request = $this->http();
        $request = $this->isEpostman($baseUrl) ? $request->asJson() : $request->asForm();
        $response = $request
            ->post($this->url($this->path('token_path', $baseUrl), $baseUrl), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $json = $this->jsonResponse($response);

        if ($this->isEpostman($baseUrl) && (! is_string($json['access_token'] ?? null) || trim($json['access_token']) === '' || ! is_numeric($json['expires_in'] ?? null) || $json['expires_in'] <= 0 || strtolower((string) ($json['token_type'] ?? '')) !== 'bearer')) {
            throw new \RuntimeException('Invalid ePostman authentication response.');
        }

        return $json;
    }

    public function accessToken(string $clientId, string $clientSecret, ?string $baseUrl = null): string
    {
        $resolvedBaseUrl = $this->resolveBaseUrl($baseUrl);
        $cacheKey = 'efaktura.sapi_sk.token.'.hash('sha256', $clientId.'|'.$clientSecret.'|'.$resolvedBaseUrl);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $payload = $this->authenticate($clientId, $clientSecret, $resolvedBaseUrl);
        $token = (string) ($payload['access_token'] ?? '');

        if ($token === '') {
            throw new \RuntimeException('SAPI-SK token response missing access_token.');
        }

        Cache::put($cacheKey, $token, $this->tokenTtlSeconds($payload));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function sendDocument(
        string $accessToken,
        string $senderParticipantId,
        array $body,
        ?string $idempotencyKey = null,
        ?string $baseUrl = null,
    ): array {
        if ($this->isEpostman($baseUrl)) {
            throw new \RuntimeException('The ePostman connection supports receiving only.');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders([
                'X-Peppol-Participant-Id' => $senderParticipantId,
                'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
            ])
            ->post($this->url(config('efaktura.providers.sapi_sk.send_path'), $baseUrl), $body);

        if ($response->failed()) {
            $response->throw();
        }

        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body()];
    }

    /**
     * @return array<string, mixed>
     */
    public function sentDocument(
        string $accessToken,
        string $senderParticipantId,
        string $documentId,
        ?string $baseUrl = null,
    ): array {
        if ($this->isEpostman($baseUrl)) {
            throw new \RuntimeException('The ePostman connection supports receiving only.');
        }
        $pathTemplate = (string) $this->sentDocumentDetailPathTemplate($baseUrl);
        if ($pathTemplate === '') {
            throw new \RuntimeException('SAPI-SK sent document detail path is not configured.');
        }

        $path = str_replace(
            '{id}',
            rawurlencode($documentId),
            $pathTemplate,
        );

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $senderParticipantId])
            ->get($this->url($path, $baseUrl));

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : ['payload' => $response->body()];
    }

    /**
     * @return array<string, mixed>
     */
    public function listReceivedDocuments(
        string $accessToken,
        string $receiverParticipantId,
        ?string $status = 'RECEIVED',
        ?int $limit = null,
        ?string $pageToken = null,
        ?string $baseUrl = null,
    ): array {
        $query = array_filter([
            'status' => $status,
            'limit' => $limit ?? (int) config('efaktura.inbound_poll_limit', 20),
            'pageToken' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $receiverParticipantId])
            ->get($this->url($this->path('receive_path', $baseUrl), $baseUrl), $query);

        $json = $this->jsonResponse($response);
        $documents = $json['documents'] ?? $json['items'] ?? null;
        if (! is_array($documents) || ! array_is_list($documents)) {
            throw new \RuntimeException('Invalid SAPI-SK received-document list.');
        }
        if (isset($json['nextPageToken']) && (! is_string($json['nextPageToken']) || strlen($json['nextPageToken']) > 8192)) {
            throw new \RuntimeException('Invalid SAPI-SK pagination token.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function receivedDocument(
        string $accessToken,
        string $receiverParticipantId,
        string $documentId,
        ?string $baseUrl = null,
    ): array {
        $path = str_replace(
            '{id}',
            rawurlencode($documentId),
            $this->path('receive_detail_path', $baseUrl),
        );

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $receiverParticipantId])
            ->get($this->url($path, $baseUrl));

        $json = $this->jsonResponse($response);
        if ($this->isEpostman($baseUrl)) {
            $metadata = $json['metadata'] ?? null;
            if (! is_array($metadata) || ($metadata['documentId'] ?? null) !== $documentId || ($metadata['receiverParticipantId'] ?? null) !== $receiverParticipantId || ! is_string($json['payload'] ?? null) || $json['payload'] === '' || ($json['payloadFormat'] ?? null) !== 'XML' || ! in_array($json['status'] ?? null, ['RECEIVED', 'ACKNOWLEDGED'], true)) {
                throw new \RuntimeException('Invalid ePostman received document or receiver.');
            }
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function acknowledgeReceived(
        string $accessToken,
        string $receiverParticipantId,
        string $documentId,
        ?string $baseUrl = null,
    ): array {
        $path = str_replace(
            '{id}',
            rawurlencode($documentId),
            $this->path('acknowledge_path', $baseUrl),
        );

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $receiverParticipantId])
            ->post($this->url($path, $baseUrl));

        $json = $this->jsonResponse($response);
        if ($this->isEpostman($baseUrl) && (($json['documentId'] ?? null) !== $documentId || ($json['status'] ?? null) !== 'ACKNOWLEDGED')) {
            throw new \RuntimeException('Invalid ePostman acknowledgement response.');
        }

        return $json;
    }

    /** A named profile is selected only for the two published, fixed origins. */
    public function isEpostman(?string $baseUrl): bool
    {
        $parts = parse_url((string) ($baseUrl ?? config('efaktura.providers.sapi_sk.base_url')));

        return is_array($parts) && in_array(strtolower((string) ($parts['host'] ?? '')), [
            'postman.slovakodata.com', 'testpostman.slovakodata.com',
        ], true);
    }

    public function forgetAccessToken(string $clientId, string $clientSecret, string $baseUrl): void
    {
        Cache::forget('efaktura.sapi_sk.token.'.hash('sha256', $clientId.'|'.$clientSecret.'|'.$this->resolveBaseUrl($baseUrl)));
    }

    public function connectionKey(string $baseUrl, string $participantId): string
    {
        return hash('sha256', ($this->isEpostman($baseUrl) ? 'epostman' : 'sapi_sk').'|'.$this->resolveBaseUrl($baseUrl).'|'.$participantId);
    }

    protected function path(string $key, ?string $baseUrl): string
    {
        if ($this->isEpostman($baseUrl)) {
            return match ($key) {
                'token_path' => '/sapi/auth/token',
                'receive_path' => '/sapi/document/receive',
                'receive_detail_path' => '/sapi/document/receive/{id}',
                'acknowledge_path' => '/sapi/document/receive/{id}/acknowledge',
                default => throw new \RuntimeException('Unsupported ePostman operation.'),
            };
        }

        return (string) config('efaktura.providers.sapi_sk.'.$key);
    }

    /** @return array<string, mixed> */
    protected function jsonResponse(Response $response): array
    {
        $response->throw();
        if (! $response->successful() || ! is_array($response->json()) || array_is_list($response->json())) {
            throw new \RuntimeException('SAPI-SK response is not a successful JSON object.');
        }

        return $response->json();
    }

    public function isRecipientNotFoundError(RequestException $exception): bool
    {
        return $exception->response?->status() === 422;
    }

    /**
     * Validate a base URL (HTTPS-only, SSRF guard, allowlist) without any
     * HTTP call - used by efaktura:doctor for ops diagnostics. Throws a
     * RuntimeException with the human-readable reason.
     */
    public function validateBaseUrl(?string $baseUrl): string
    {
        return $this->resolveBaseUrl($baseUrl);
    }

    /**
     * Effective sent-document detail path: the CPDS preset matching the base
     * URL wins over the global EFAKTURA_SAPI_SEND_DETAIL_PATH - two merchants
     * on different postmen can each track their own status endpoint.
     */
    public function sentDocumentDetailPathTemplate(?string $baseUrl = null): ?string
    {
        $preset = EfakturaCpdsProvider::detailPathForBaseUrl(
            $baseUrl ?? (string) config('efaktura.providers.sapi_sk.base_url'),
        );
        if ($preset !== null) {
            return $preset;
        }

        $global = (string) config('efaktura.providers.sapi_sk.send_detail_path', '');

        return $global !== '' ? $global : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenTtlSeconds(array $payload): int
    {
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        return max(60, $expiresIn - 60);
    }

    protected function http(): PendingRequest
    {
        // The base URL is validated host by host (resolveBaseUrl); a redirect
        // must not carry the request to a host that was never checked.
        return Http::timeout((int) config('efaktura.providers.sapi_sk.timeout_seconds', 30))
            ->withoutRedirecting()
            ->acceptJson();
    }

    protected function url(string $path, ?string $baseUrl = null): string
    {
        return $this->resolveBaseUrl($baseUrl).'/'.ltrim($path, '/');
    }

    protected function resolveBaseUrl(?string $baseUrl): string
    {
        $candidate = rtrim((string) ($baseUrl ?? config('efaktura.providers.sapi_sk.base_url')), '/');
        if ($candidate === '') {
            throw new \RuntimeException('SAPI-SK base URL is not configured for this company.');
        }

        $parts = parse_url($candidate);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new \RuntimeException('SAPI-SK base URL must be a valid HTTPS URL.');
        }

        $host = strtolower((string) $parts['host']);
        if ($this->isEpostman($candidate) && (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) || ! in_array($parts['path'] ?? '', ['', '/'], true))) {
            throw new \RuntimeException('The ePostman connection requires its fixed HTTPS origin.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('SAPI-SK base URL must not use an IP address.');
        }

        $this->assertResolvablePublicHost($host);
        $this->assertAllowedSapiHost($host);

        $port = isset($parts['port']) && $parts['port'] !== 443 ? ':'.$parts['port'] : '';

        return 'https://'.$host.$port;
    }

    /**
     * Operator-verified hosts: the env allowlist plus active CPDS presets.
     *
     * @return list<string>
     */
    protected function trustedHosts(): array
    {
        $configHosts = array_map('strtolower', (array) config('efaktura.allowed_sapi_hosts', []));

        return array_values(array_unique(array_merge($configHosts, EfakturaCpdsProvider::allowedHosts(), ['postman.slovakodata.com', 'testpostman.slovakodata.com'])));
    }

    protected function assertResolvablePublicHost(string $host): void
    {
        if (in_array($host, $this->trustedHosts(), true)) {
            return;
        }

        $ips = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                    if (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        if ($ips === []) {
            throw new \RuntimeException('SAPI-SK base URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('SAPI-SK base URL must not resolve to a private or reserved address.');
            }
        }
    }

    protected function assertAllowedSapiHost(string $host): void
    {
        if (in_array($host, $this->trustedHosts(), true)) {
            return;
        }

        // Presets only ADD trusted hosts - the open-by-default behaviour
        // below is keyed on the env allowlist alone, so adding a preset
        // never locks out merchants with a custom (non-preset) CPDS.
        $allowedHosts = array_map('strtolower', (array) config('efaktura.allowed_sapi_hosts', []));

        $globalBase = rtrim((string) config('efaktura.providers.sapi_sk.base_url'), '/');
        $globalHost = is_string($globalBase) && $globalBase !== ''
            ? strtolower((string) parse_url($globalBase, PHP_URL_HOST))
            : '';

        if ($globalHost !== '' && $host === $globalHost) {
            return;
        }

        if ($allowedHosts === [] && $globalHost === '') {
            return;
        }

        throw new \RuntimeException('SAPI-SK base URL host is not in the allowed provider list.');
    }
}
