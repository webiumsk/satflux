<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

class StripeService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get Stripe settings for a store.
     *
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Stripe settings (keys are masked)
     */
    public function getSettings(string $storeId, ?string $userApiKey = null): array
    {
        return $this->withUserKey($userApiKey, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/stripe/settings");
        });
    }

    /**
     * Update Stripe settings.
     *
     * @param string $storeId BTCPay store ID
     * @param array $data Settings (enabled, publishableKey, secretKey, settlementCurrency, advancedConfig)
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Updated settings (keys masked)
     */
    public function updateSettings(string $storeId, array $data, ?string $userApiKey = null): array
    {
        return $this->withUserKey($userApiKey, function () use ($storeId, $data) {
            return $this->client->put("/api/v1/stores/{$storeId}/stripe/settings", $data);
        });
    }

    /**
     * Delete Stripe credentials.
     *
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return void
     */
    public function deleteSettings(string $storeId, ?string $userApiKey = null): void
    {
        $this->withUserKey($userApiKey, function () use ($storeId) {
            $this->client->delete("/api/v1/stores/{$storeId}/stripe/settings");
        });
    }

    /**
     * Test Stripe connection.
     *
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return array { success, message, mode }
     */
    public function testConnection(string $storeId, ?string $userApiKey = null): array
    {
        return $this->withUserKey($userApiKey, function () use ($storeId) {
            return $this->client->post("/api/v1/stores/{$storeId}/stripe/test", []);
        });
    }

    /**
     * Register Stripe webhook.
     *
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return array { success, message }
     */
    public function registerWebhook(string $storeId, ?string $userApiKey = null): array
    {
        return $this->withUserKey($userApiKey, function () use ($storeId) {
            return $this->client->post("/api/v1/stores/{$storeId}/stripe/webhook/register", []);
        });
    }

    /**
     * Get webhook status.
     *
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return array { configured, webhookId, webhookUrl, message }
     */
    public function getWebhookStatus(string $storeId, ?string $userApiKey = null): array
    {
        return $this->withUserKey($userApiKey, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/stripe/webhook/status");
        });
    }

    /**
     * Execute a closure with optional user API key, then restore original key.
     */
    protected function withUserKey(?string $userApiKey, callable $fn)
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $fn();
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}
