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
        return rtrim(config('app.url', env('APP_URL', '')), '/') . '/api/webhooks/btcpay';
    }

    /**
     * Create a webhook for a store in BTCPay Server.
     *
     * @param string $btcpayStoreId BTCPay store ID
     * @param string|null $userApiKey Optional user API key
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

            if (!$id || !$secret) {
                throw new BtcPayException(
                    'BTCPay webhook creation did not return id or secret: ' . json_encode($result),
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
     *
     * @return array
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
            if ($userApiKey && $originalApiKey !== null) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}
