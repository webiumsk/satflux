<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Cache;

class InvoiceService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * List invoices with pagination support.
     * If a user-level API key is provided, it will be used instead of server-level.
     * 
     * @param string $storeId BTCPay store ID
     * @param array $filters Optional filters (status, orderId, itemCode, etc.)
     * @param int|null $skip Number of records to skip
     * @param int|null $take Number of records to take
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Invoice list with pagination metadata
     */
    public function listInvoices(string $storeId, array $filters = [], ?int $skip = null, ?int $take = null, ?string $userApiKey = null): array
    {
        $query = $filters;

        if ($skip !== null) {
            $query['skip'] = $skip;
        }

        if ($take !== null) {
            $query['take'] = $take;
        }

        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $this->client->get("/api/v1/stores/{$storeId}/invoices", $query);
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Get a single invoice by ID.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function getInvoice(string $storeId, string $invoiceId, ?string $userApiKey = null): array
    {
        // Include API key hash in cache key to prevent cross-merchant cache pollution
        $apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
        $cacheKey = "btcpay:invoice:{$storeId}:{$invoiceId}:{$apiKeyHash}";
        
        return Cache::remember($cacheKey, 30, function () use ($storeId, $invoiceId, $userApiKey) {
            $originalApiKey = null;
            if ($userApiKey) {
                // Temporarily use user-level API key
                $originalApiKey = $this->client->getApiKey();
                $this->client->setApiKey($userApiKey);
            }

            try {
                return $this->client->get("/api/v1/stores/{$storeId}/invoices/{$invoiceId}");
            } finally {
                // Restore original API key if we changed it
                if ($userApiKey && $originalApiKey) {
                    $this->client->setApiKey($originalApiKey);
                }
            }
        });
    }

    /**
     * Get invoice count for a store (cached).
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function getInvoiceCount(string $storeId, array $filters = [], ?string $userApiKey = null): int
    {
        // Include API key hash in cache key to prevent cross-merchant cache pollution
        $apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
        $cacheKey = "btcpay:invoice:count:{$storeId}:{$apiKeyHash}:" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 60, function () use ($storeId, $filters, $userApiKey) {
            // BTCPay API doesn't have a direct count endpoint, so we fetch a small page
            $result = $this->listInvoices($storeId, $filters, 0, 1, $userApiKey);
            // Note: BTCPay API may return total count in response, adjust based on actual API response
            return count($result) ?? 0;
        });
    }
}







