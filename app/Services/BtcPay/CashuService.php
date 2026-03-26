<?php

namespace App\Services\BtcPay;

class CashuService
{
    public function __construct(protected BtcPayClient $client)
    {
    }

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

    public function getSettings(string $storeId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/plugins/cashu/settings");
        });
    }

    public function saveSettings(string $storeId, array $data, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $data) {
            // $data = ['mintUrl' => ..., 'unit' => ..., 'lightningAddress' => ..., 'enabled' => true]
            return $this->client->put("/api/v1/stores/{$storeId}/plugins/cashu/settings", $data);
        });
    }

    public function listPayments(string $storeId, string $apiKey, array $params = []): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $params) {
            // $params = ['limit' => ..., 'offset' => ..., 'settlementState' => 'SETTLED'|'PENDING'|'FAILED']
            return $this->client->get("/api/v1/stores/{$storeId}/plugins/cashu/payments", $params);
        });
    }

    public function retryPayment(string $storeId, string $quoteId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $quoteId) {
            return $this->client->post(
                "/api/v1/stores/{$storeId}/plugins/cashu/payments/{$quoteId}/retry",
                []
            );
        });
    }
}

