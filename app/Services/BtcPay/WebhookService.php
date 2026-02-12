<?php

namespace App\Services\BtcPay;

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
        return rtrim(config('app.url', env('APP_URL', '')), '/') . '/api/webhooks/btcpay';
    }

    /**
     * Create a webhook for a store in BTCPay Server.
     * Subscribes to all events. Uses the panel's webhook URL.
     *
     * @param string $btcpayStoreId BTCPay store ID
     * @param string|null $userApiKey Optional user API key (uses client's current key if null)
     * @return array{id: string, secret: string} Webhook id and secret from BTCPay
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
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
            // authorizedEvents: omit or empty = all events (BTCPay default)
            $body = [
                'url' => $url,
            ];
            $result = $this->client->post("/api/v1/stores/{$btcpayStoreId}/webhooks", $body);

            $id = $result['id'] ?? $result['webhookId'] ?? null;
            $secret = $result['secret'] ?? null;

            if (!$id || !$secret) {
                throw new Exceptions\BtcPayException(
                    'BTCPay webhook creation did not return id or secret: ' . json_encode($result),
                    500
                );
            }

            return ['id' => $id, 'secret' => $secret];
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Delete a webhook from BTCPay Server.
     *
     * @param string $btcpayStoreId BTCPay store ID
     * @param string $webhookId BTCPay webhook ID
     * @param string|null $userApiKey Optional user API key
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
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * List webhooks for a store.
     *
     * @param string $btcpayStoreId BTCPay store ID
     * @param string|null $userApiKey Optional user API key
     * @return array List of webhook data from BTCPay
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
            return is_array($result) ? $result : [];
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}
