<?php

namespace App\Services\Invoicing;

use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;
use Throwable;

/** A transient, fixed-host GET bridge. No credentials or invoice data are retained. */
class ZapriteMigrationService
{
    public const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;

    /** @return array{status: int, body: array<string, mixed>} */
    public function fetchPage(#[SensitiveParameter] string $apiKey, int $page): array
    {
        $stream = null;
        $deadline = $this->nowNanoseconds() + 20_000_000_000;

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->connectTimeout(5)
                ->timeout(20)
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'read_timeout' => 1,
                    'verify' => true,
                ])
                ->get('https://api.zaprite.com/v1/orders', [
                    'includePending' => 'true',
                    'page' => $page,
                ]);

            $stream = $response->toPsrResponse()->getBody();
            if (in_array($response->status(), [401, 403], true)) {
                return $this->failure('zaprite_auth_failed', 422);
            }
            if ($response->status() === 429) {
                return $this->failure('zaprite_rate_limited', 429);
            }
            if ($response->status() !== 200) {
                return $this->failure('zaprite_unavailable', 502);
            }

            $body = '';
            while (! $this->streamAtEnd($stream)) {
                // A per-read socket timeout alone permits an endless drip-feed.
                // Reserve one bounded read inside the overall request budget.
                if ($this->nowNanoseconds() + 1_000_000_000 >= $deadline) {
                    return $this->failure('zaprite_unavailable', 502);
                }
                $part = $stream->read(min(8192, self::MAX_RESPONSE_BYTES + 1 - strlen($body)));
                if ($this->nowNanoseconds() >= $deadline || ($part === '' && ! $this->streamAtEnd($stream))) {
                    return $this->failure('zaprite_unavailable', 502);
                }
                if ($part === '') {
                    break;
                }
                $body .= $part;
                if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                    return $this->failure('zaprite_limit_exceeded', 422);
                }
            }

            try {
                $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->failure('zaprite_response_invalid', 502);
            }
            if (! is_array($data) || $this->containsSecret($data, $apiKey)) {
                return $this->failure('zaprite_response_invalid', 502);
            }
            if (! $this->validPage($data, $page)) {
                return $this->failure('zaprite_response_invalid', 502);
            }
            if ($data['meta']['itemsCount'] > 2000 || $data['meta']['pagesCount'] > 20) {
                return $this->failure('zaprite_limit_exceeded', 422);
            }

            return ['status' => 200, 'body' => ['data' => ['items' => $data['items'], 'meta' => $data['meta']]]];
        } catch (Throwable) {
            // Provider exceptions can embed Authorization headers and response bodies.
            // They must never escape into Laravel's exception reporting or response.
            return $this->failure('zaprite_unavailable', 502);
        } finally {
            try {
                $stream?->close();
            } catch (Throwable) {
                // Closing an unsuccessful remote stream must not report its context.
            }
        }
    }

    protected function nowNanoseconds(): int
    {
        return (int) hrtime(true);
    }

    /** @phpstan-impure Stream position changes during reads. */
    private function streamAtEnd(StreamInterface $stream): bool
    {
        return $stream->eof();
    }

    /** @param array<string, mixed> $data */
    private function validPage(array $data, int $requestedPage): bool
    {
        $items = $data['items'] ?? null;
        $meta = $data['meta'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || count($items) > 100 || ! is_array($meta)) {
            return false;
        }
        foreach (['itemsCount', 'pagesCount', 'page', 'perPage'] as $field) {
            if (! isset($meta[$field]) || ! is_int($meta[$field])) {
                return false;
            }
        }
        if ($meta['itemsCount'] < 0 || $meta['pagesCount'] < 0 || $meta['page'] !== $requestedPage
            || $meta['perPage'] < 1 || $meta['perPage'] > 100) {
            return false;
        }

        $expectedPages = (int) ceil($meta['itemsCount'] / $meta['perPage']);
        if ($meta['itemsCount'] === 0) {
            return $requestedPage === 1 && in_array($meta['pagesCount'], [0, 1], true) && $items === [];
        }
        if ($meta['pagesCount'] !== $expectedPages || $requestedPage > $expectedPages
            || count($items) !== min($meta['perPage'], $meta['itemsCount'] - ($requestedPage - 1) * $meta['perPage'])) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null) || $item['id'] === ''
                || ! is_string($item['orgId'] ?? null) || $item['orgId'] === ''
                || ! is_string($item['orderType'] ?? null) || $item['orderType'] === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $value */
    private function containsSecret(array $value, #[SensitiveParameter] string $apiKey): bool
    {
        foreach ($value as $key => $item) {
            if ((is_string($key) && str_contains($key, $apiKey))
                || (is_string($item) && str_contains($item, $apiKey))
                || (is_array($item) && $this->containsSecret($item, $apiKey))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{status: int, body: array{code: string}} */
    private function failure(string $code, int $status): array
    {
        return ['status' => $status, 'body' => ['code' => $code]];
    }
}
