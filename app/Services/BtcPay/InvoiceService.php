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
     * 
     * @param string $storeId BTCPay store ID
     * @param array $filters Optional filters (status, orderId, itemCode, etc.)
     * @param int|null $skip Number of records to skip
     * @param int|null $take Number of records to take
     * @return array Invoice list with pagination metadata
     */
    public function listInvoices(string $storeId, array $filters = [], ?int $skip = null, ?int $take = null): array
    {
        $query = $filters;

        if ($skip !== null) {
            $query['skip'] = $skip;
        }

        if ($take !== null) {
            $query['take'] = $take;
        }

        return $this->client->get("/api/v1/stores/{$storeId}/invoices", $query);
    }

    /**
     * Get a single invoice by ID.
     */
    public function getInvoice(string $storeId, string $invoiceId): array
    {
        $cacheKey = "btcpay:invoice:{$storeId}:{$invoiceId}";
        
        return Cache::remember($cacheKey, 30, function () use ($storeId, $invoiceId) {
            return $this->client->get("/api/v1/stores/{$storeId}/invoices/{$invoiceId}");
        });
    }

    /**
     * Get invoice count for a store (cached).
     */
    public function getInvoiceCount(string $storeId, array $filters = []): int
    {
        $cacheKey = "btcpay:invoice:count:{$storeId}:" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 60, function () use ($storeId, $filters) {
            // BTCPay API doesn't have a direct count endpoint, so we fetch a small page
            $result = $this->listInvoices($storeId, $filters, 0, 1);
            // Note: BTCPay API may return total count in response, adjust based on actual API response
            return count($result) ?? 0;
        });
    }
}

