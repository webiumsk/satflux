<?php

namespace App\Support;

class PresenterUrlNormalizer
{
    /**
     * BTCPay may return a relative URL or one with an internal request host (Docker) when
     * presenter-token is called server-to-server. Always expose the public BTCPay base URL.
     *
     * @param  array<string, mixed>  $tokenPayload
     * @return array<string, mixed>
     */
    public function normalize(array $tokenPayload, string $raffleId): array
    {
        $token = (string) ($tokenPayload['token'] ?? '');
        $publicBase = rtrim((string) config('services.btcpay.public_url', ''), '/');

        if ($publicBase === '' || $token === '') {
            return $tokenPayload;
        }

        $expectedPath = $this->normalizePath('/raffle/'.$raffleId.'/present');
        $presenterUrl = (string) ($tokenPayload['presenterUrl'] ?? '');
        $publicOrigin = $this->normalizeHttpOrigin($publicBase);

        $needsRebuild = true;
        if ($presenterUrl !== '' && str_starts_with($presenterUrl, 'http')) {
            $parsed = parse_url($presenterUrl);
            $parsedPath = $this->normalizePath(isset($parsed['path']) ? (string) $parsed['path'] : null);
            $pathOk = $parsedPath === $expectedPath;
            $presenterOrigin = $this->normalizeHttpOrigin($presenterUrl);
            $originOk = $publicOrigin !== null && $presenterOrigin !== null
                && strcasecmp($publicOrigin, $presenterOrigin) === 0;
            $needsRebuild = ! ($pathOk && $originOk);
        }

        if ($needsRebuild) {
            $tokenPayload['presenterUrl'] = $publicBase.$expectedPath.'?token='.rawurlencode($token);
        }

        return $tokenPayload;
    }

    public function normalizeHttpOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443)) {
            return "{$scheme}://{$host}";
        }

        return "{$scheme}://{$host}:{$port}";
    }

    protected function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        return '/'.trim($path, '/');
    }
}
