<?php

namespace App\Services;

use App\Models\Store;
use App\Services\BtcPay\WebhookService;
use Illuminate\Support\Facades\DB;

class StoreWebhookProvisioningService
{
    public function __construct(private WebhookService $webhookService) {}

    /**
     * Serialize retries with the immediate post-creation attempt. The store
     * row stays locked during the BTCPay calls - acceptable for a one-off
     * webhook create, and it keeps the replace idempotent.
     */
    public function provisionMissing(string $storeId): bool
    {
        return DB::transaction(function () use ($storeId) {
            $store = Store::whereKey($storeId)->lockForUpdate()->first();
            if (! $store || $store->btcpay_webhook_id !== null) {
                return false;
            }

            $data = $this->webhookService->replacePanelWebhookForStore($store->btcpay_store_id, null);
            $store->update([
                'btcpay_webhook_id' => $data['id'],
                'webhook_secret' => $data['secret'],
            ]);

            return true;
        });
    }
}
