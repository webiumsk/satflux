<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Support\Http\OutboundUrlGuard;

class WebhookService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get the panel webhook URL that BTCPay will call.
     */
    public function getWebhookUrl(): string
    {
        $base = config('services.btcpay.webhook_base_url');
        if (! is_string($base) || trim($base) === '') {
            throw new \RuntimeException('BTCPAY_WEBHOOK_BASE_URL must explicitly identify the reachable Satflux callback service.');
        }
        $url = rtrim(trim($base), '/').'/api/webhooks/btcpay';
        $this->validateDestination($url);

        return $url;
    }

    public function validateDestination(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array(strtolower($parts['scheme']), ['https', 'http'], true)) {
            throw new \RuntimeException('BTCPay callbacks need an absolute HTTP(S) URL without credentials, query, or fragment.');
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $origin = $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        $private = ! str_contains($host, '.') || str_starts_with($host, '[')
            || in_array($host, ['localhost', '127.0.0.1'], true)
            || preg_match('/\.(local|lan|internal)$/i', $host)
            || (filter_var($host, FILTER_VALIDATE_IP) && ! OutboundUrlGuard::isPublicIp($host));
        if (($private || $scheme !== 'https') && ! in_array($origin, config('services.btcpay.webhook_private_origins', []), true)) {
            throw new \RuntimeException('Private or HTTP callback origins require BTCPAY_WEBHOOK_PRIVATE_ORIGINS and a narrow BTCPay SSRF exception.');
        }
    }

    /**
     * Create a webhook for a store in BTCPay Server.
     *
     * @param  string  $btcpayStoreId  BTCPay store ID
     * @param  string|null  $userApiKey  Optional user API key
     * @return array{id: string, secret: string}
     */
    public function createWebhook(string $btcpayStoreId, ?string $userApiKey = null): array
    {
        $url = $this->getWebhookUrl();

        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $body = ['url' => $url];
            $result = $this->client->post("/api/v1/stores/{$btcpayStoreId}/webhooks", $body, retry: false);

            $id = $result['id'] ?? $result['webhookId'] ?? null;
            $secret = $result['secret'] ?? null;

            if (! $id || ! $secret) {
                throw new BtcPayException(
                    'BTCPay webhook creation did not return an ID and signing secret',
                    500
                );
            }

            return ['id' => $id, 'secret' => $secret];
        } finally {
            if ($userApiKey && $originalApiKey !== null) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Delete a webhook from BTCPay Server.
     */
    public function deleteWebhook(string $btcpayStoreId, string $webhookId, ?string $userApiKey = null): void
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $this->client->delete("/api/v1/stores/{$btcpayStoreId}/webhooks/{$webhookId}");
        } finally {
            if ($userApiKey && $originalApiKey !== null) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * List webhooks for a store.
     */
    public function listWebhooks(string $btcpayStoreId, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $result = $this->client->get("/api/v1/stores/{$btcpayStoreId}/webhooks");

            return $this->normalizeWebhooksListResponse(is_array($result) ? $result : []);
        } finally {
            if ($userApiKey && $originalApiKey !== null) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /** Read delivery status without exposing response bodies or token-bearing error messages. */
    public function deliveryDiagnostic(string $storeId, string $webhookId, ?string $merchantKey = null): string
    {
        try {
            $deliveries = $this->client->withUserKey($merchantKey, fn () => $this->client->get("/api/v1/stores/{$storeId}/webhooks/{$webhookId}/deliveries", ['count' => 1]));
            if ($deliveries === []) {
                return 'No recorded delivery; reachability has not been tested';
            }
            $last = $deliveries[0];
            if (($last['status'] ?? '') === 'HttpSuccess') {
                return 'Last recorded delivery succeeded';
            }
            $error = strtolower((string) ($last['errorMessage'] ?? ''));
            if (str_contains($error, 'allowed network address')) {
                return 'SSRF blocked: review the destination or authorize an exact host/IP and port';
            }
            if ($code = $last['httpCode'] ?? null) {
                return 'Endpoint returned HTTP '.(int) $code;
            }
            if (str_contains($error, 'certificate') || str_contains($error, 'ssl') || str_contains($error, 'tls')) {
                return 'TLS/certificate failure';
            }

            return 'Delivery failed or is pending: check DNS, connectivity, timeout, and BTCPay delivery details';
        } catch (\Throwable) {
            return 'Delivery diagnostics unavailable; check webhook authorization and BTCPay availability';
        }
    }

    /**
     * Normalize Greenfield list response to a list of webhook objects.
     *
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    public function normalizeWebhooksListResponse(array $result): array
    {
        if ($result === []) {
            return [];
        }
        if (isset($result['webhooks']) && is_array($result['webhooks'])) {
            return array_values($result['webhooks']);
        }
        $keys = array_keys($result);
        if ($keys === range(0, count($result) - 1)) {
            return $result;
        }
        if (isset($result['id'])) {
            return [$result];
        }

        return [];
    }

    /**
     * Compare callback origins case-insensitively, preserving path case and ignoring trailing slashes.
     */
    public function webhookUrlsMatch(string $a, string $b): bool
    {
        $normalize = static function (string $url): ?string {
            $parts = parse_url($url);
            if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
                return null;
            }

            return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
                .(isset($parts['port']) ? ':'.$parts['port'] : '')
                .rtrim($parts['path'] ?? '', '/')
                .(isset($parts['query']) ? '?'.$parts['query'] : '');
        };

        return $normalize($a) !== null && $normalize($a) === $normalize($b);
    }

    /**
     * Delete every store webhook whose URL matches this app's panel webhook URL.
     * Used to remove duplicates and orphans before creating a single canonical webhook.
     *
     * @return int Number of webhooks deleted
     */
    public function deletePanelWebhooksForStore(string $btcpayStoreId, ?string $userApiKey = null): int
    {
        $panelUrl = $this->getWebhookUrl();
        $list = $this->listWebhooks($btcpayStoreId, $userApiKey);
        $deleted = 0;

        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? $item['webhookId'] ?? null;
            $url = (string) ($item['url'] ?? $item['Url'] ?? '');
            if (is_string($id) && $id !== '' && $this->webhookUrlsMatch($url, $panelUrl)) {
                $this->deleteWebhook($btcpayStoreId, $id, $userApiKey);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Reuse the canonical subscription and signing secret. Refuse duplicates or disabled subscriptions.
     * An orphan is adopted in place; unrelated destinations are never changed.
     *
     * @return array{id: string, secret: string}
     */
    public function replacePanelWebhookForStore(string $btcpayStoreId, ?string $userApiKey = null, ?string $knownId = null, ?string $knownSecret = null): array
    {
        $url = $this->getWebhookUrl();
        $entries = $this->listWebhooks($btcpayStoreId, $userApiKey);
        $matches = array_values(array_filter($entries,
            fn (array $item) => $this->webhookUrlsMatch((string) ($item['url'] ?? ''), $url)));
        if (count($matches) > 1) {
            throw new \RuntimeException('Duplicate panel webhooks need operator review. Run btcpay:reconcile-webhooks --dry-run.');
        }
        if ($matches === []) {
            if ($knownId && collect($entries)->contains(fn ($entry) => ($entry['id'] ?? null) === $knownId)) {
                throw new \RuntimeException('The known subscription uses another destination. Review btcpay:reconcile-webhooks before updating it; no duplicate was created.');
            }

            return $this->createWebhook($btcpayStoreId, $userApiKey);
        }
        $item = $matches[0];
        if (! ($item['enabled'] ?? true)) {
            throw new \RuntimeException('The matching webhook is disabled. Review its purpose before re-enabling it.');
        }
        $id = $item['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('BTCPay returned a webhook without an ID.');
        }
        // BTCPay does not reveal signing secrets in GET responses. Retain the local
        // secret if known; an orphan is adopted in place using a new secret.
        $known = $knownId === $id && filled($knownSecret);
        $secret = $known ? $knownSecret : bin2hex(random_bytes(32));
        if (! $known) {
            $payload = [
                'url' => $url, 'secret' => $secret, 'enabled' => true,
                'automaticRedelivery' => $item['automaticRedelivery'] ?? true,
                'authorizedEvents' => $item['authorizedEvents'] ?? ['everything' => true],
            ];
            $this->client->withUserKey($userApiKey, fn () => $this->client->put("/api/v1/stores/{$btcpayStoreId}/webhooks/{$id}", $payload));
        }

        return ['id' => $id, 'secret' => $secret];
    }
}
