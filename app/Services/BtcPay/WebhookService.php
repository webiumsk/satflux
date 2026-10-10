<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

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
        return rtrim(config('app.url', env('APP_URL', '')), '/').'/api/webhooks/btcpay';
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
        if ($url === '/api/webhooks/btcpay' || $url === '') {
            Log::warning('WebhookService: APP_URL not set, webhook URL may be invalid', [
                'store_id' => $btcpayStoreId,
            ]);
        }

        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $body = ['url' => $url];
            $result = $this->client->post("/api/v1/stores/{$btcpayStoreId}/webhooks", $body);

            $id = $result['id'] ?? $result['webhookId'] ?? null;
            $secret = $result['secret'] ?? null;

            if (! $id || ! $secret) {
                throw new BtcPayException(
                    'BTCPay webhook creation did not return id or secret: '.json_encode($result),
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
     * Whether two webhook URLs point to the same Satflux panel endpoint (case-insensitive, ignores trailing slashes).
     */
    public function webhookUrlsMatch(string $a, string $b): bool
    {
        return rtrim(strtolower($a), '/') === rtrim(strtolower($b), '/');
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
     * Remove all panel-URL webhooks for the store, then create exactly one new webhook and return id + secret.
     * Ensures BTCPay and Satflux agree on a single signing secret (fixes duplicate webhooks / mismatched DB secrets).
     *
     * @return array{id: string, secret: string}
     */
    public function replacePanelWebhookForStore(string $btcpayStoreId, ?string $userApiKey = null): array
    {
        $removed = $this->deletePanelWebhooksForStore($btcpayStoreId, $userApiKey);
        if ($removed > 0) {
            Log::info('WebhookService: removed panel URL webhook(s) before recreate', [
                'btcpay_store_id' => $btcpayStoreId,
                'removed' => $removed,
            ]);
        }

        return $this->createWebhook($btcpayStoreId, $userApiKey);
    }
}
