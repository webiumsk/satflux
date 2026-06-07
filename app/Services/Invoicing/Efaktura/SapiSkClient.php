<?php

namespace App\Services\Invoicing\Efaktura;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
        $response = $this->http()
            ->asForm()
            ->post($this->url(config('efaktura.providers.sapi_sk.token_path'), $baseUrl), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();

        return $response->json();
    }

    public function accessToken(string $clientId, string $clientSecret, ?string $baseUrl = null): string
    {
        $resolvedBaseUrl = $this->resolveBaseUrl($baseUrl);
        $cacheKey = 'efaktura.sapi_sk.token.'.sha1($clientId.'|'.$resolvedBaseUrl);

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
            ->get($this->url(config('efaktura.providers.sapi_sk.receive_path'), $baseUrl), $query);

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : ['documents' => []];
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
            (string) config('efaktura.providers.sapi_sk.receive_detail_path'),
        );

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $receiverParticipantId])
            ->get($this->url($path, $baseUrl));

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : ['payload' => $response->body()];
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
            (string) config('efaktura.providers.sapi_sk.acknowledge_path'),
        );

        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders(['X-Peppol-Participant-Id' => $receiverParticipantId])
            ->post($this->url($path, $baseUrl));

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : ['status' => 'acknowledged'];
    }

    public function isRecipientNotFoundError(RequestException $exception): bool
    {
        return $exception->response?->status() === 422;
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
        return Http::timeout((int) config('efaktura.providers.sapi_sk.timeout_seconds', 30))
            ->acceptJson();
    }

    protected function url(string $path, ?string $baseUrl = null): string
    {
        return $this->resolveBaseUrl($baseUrl).'/'.ltrim($path, '/');
    }

    protected function resolveBaseUrl(?string $baseUrl): string
    {
        $base = rtrim((string) ($baseUrl ?? config('efaktura.providers.sapi_sk.base_url')), '/');
        if ($base === '') {
            throw new \RuntimeException('SAPI-SK base URL is not configured for this company.');
        }

        return $base;
    }
}
