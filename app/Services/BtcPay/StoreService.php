<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Cache;

class StoreService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new store in BTCPay Server.
     */
    public function createStore(array $data): array
    {
        return $this->client->post('/api/v1/stores', $data);
    }

    /**
     * Get a store by ID.
     */
    public function getStore(string $storeId): array
    {
        $cacheKey = "btcpay:store:{$storeId}";
        
        return Cache::remember($cacheKey, 60, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}");
        });
    }

    /**
     * List all stores (from BTCPay - application layer filters by user mapping).
     */
    public function listStores(): array
    {
        return $this->client->get('/api/v1/stores');
    }

    /**
     * Update store settings (safe fields only).
     */
    public function updateStore(string $storeId, array $data): array
    {
        $result = $this->client->put("/api/v1/stores/{$storeId}", $data);
        
        // Clear cache
        Cache::forget("btcpay:store:{$storeId}");
        
        return $result;
    }
}

