<?php

namespace App\Services\Invoicing\Efaktura;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SapiSkClient
{
    /**
     * @return array<string, mixed>
     */
    public function authenticate(string $clientId, string $clientSecret): array
    {
        $response = $this->http()
            ->asForm()
            ->post($this->url(config('efaktura.providers.sapi_sk.token_path')), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();

        return $response->json();
    }

    public function accessToken(string $clientId, string $clientSecret): string
    {
        $cacheKey = 'efaktura.sapi_sk.token.'.sha1($clientId);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $payload = $this->authenticate($clientId, $clientSecret);
        $token = (string) ($payload['access_token'] ?? '');

        if ($token === '') {
            throw new \RuntimeException('SAPI-SK token response missing access_token.');
        }

        Cache::put($cacheKey, $token, $this->tokenTtlSeconds($payload));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenTtlSeconds(array $payload): int
    {
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        return max(60, $expiresIn - 60);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendDocument(
        string $accessToken,
        string $peppolParticipantId,
        string $ublXml,
        ?string $idempotencyKey = null,
    ): array {
        $response = $this->http()
            ->withToken($accessToken)
            ->withHeaders([
                'X-Peppol-Participant-Id' => $peppolParticipantId,
                'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
                'Content-Type' => 'application/xml',
                'Accept' => 'application/json',
            ])
            ->withBody($ublXml, 'application/xml')
            ->post($this->url(config('efaktura.providers.sapi_sk.send_path')));

        if ($response->failed()) {
            $response->throw();
        }

        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body()];
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('efaktura.providers.sapi_sk.timeout_seconds', 30))
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        $base = rtrim((string) config('efaktura.providers.sapi_sk.base_url'), '/');
        if ($base === '') {
            throw new \RuntimeException('EFAKTURA_SAPI_BASE_URL is not configured.');
        }

        return $base.'/'.ltrim($path, '/');
    }
}
