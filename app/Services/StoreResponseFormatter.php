<?php

namespace App\Services;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Support\Collection;

/**
 * Single place that decides what a store looks like in API responses.
 * Note: btcpay_store_id is included deliberately - the Pay Button embed
 * snippet and API key generation need the real BTCPay store ID (see CLAUDE.md).
 */
class StoreResponseFormatter
{
    /**
     * Format from the local Store model only (BTCPay API unavailable or not needed).
     *
     * @return array<string, mixed>
     */
    public function fromLocal(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'btcpay_store_id' => $store->btcpay_store_id,
            'default_currency' => $store->default_currency ?? 'EUR',
            'timezone' => $store->timezone ?? 'Europe/Vienna',
            'preferred_exchange' => $store->preferred_exchange ?? 'kraken',
            'wallet_type' => $store->wallet_type,
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
            'logo_url' => null, // Not available from local DB only (would need BTCPay API)
            'anyone_can_create_invoice' => false, // Unknown when BTCPay API failed; safe default
            'checklist_items' => $this->checklistItemsPayload($store),
            'wallet_connection' => $this->walletConnectionPayload($store, $store->walletConnection),
        ];
    }

    /**
     * Format BTCPay store data merged with local Store metadata.
     *
     * @param  array<string, mixed>  $btcpayStore
     * @return array<string, mixed>
     */
    public function fromBtcPay(array $btcpayStore, Store $localStore): array
    {
        $data = [
            'id' => $localStore->id,
            'name' => $btcpayStore['name'] ?? $localStore->name,
            // Local store values first, fallback to BTCPay values
            'default_currency' => $localStore->default_currency ?? ($btcpayStore['defaultCurrency'] ?? 'EUR'),
            'timezone' => $localStore->timezone ?? ($btcpayStore['timeZone'] ?? 'Europe/Vienna'),
            'preferred_exchange' => $localStore->preferred_exchange ?? ($btcpayStore['preferredExchange'] ?? 'kraken'),
            'wallet_type' => $localStore->wallet_type,
            'created_at' => $localStore->created_at,
            'updated_at' => $localStore->updated_at,
            'checklist_items' => $this->checklistItemsPayload($localStore),
            'wallet_connection' => $this->walletConnectionPayload($localStore, $localStore->walletConnection),
            'btcpay_store_id' => $localStore->btcpay_store_id,
            // Pay Button: anyone can create invoice (needed for Pay Button page)
            'anyone_can_create_invoice' => $btcpayStore['anyoneCanCreateInvoice'] ?? false,
        ];

        // BTCPay-specific fields that are safe to expose
        if (isset($btcpayStore['website'])) {
            $data['website'] = $btcpayStore['website'];
        }
        if (isset($btcpayStore['archived'])) {
            $data['archived'] = $btcpayStore['archived'];
        }
        if (isset($btcpayStore['logoUrl'])) {
            $data['logo_url'] = $btcpayStore['logoUrl'];
        } elseif (isset($btcpayStore['logo_url'])) {
            $data['logo_url'] = $btcpayStore['logo_url'];
        }

        return $data;
    }

    /**
     * Wallet payload: Cashu has no wallet_connections row but is configured via BTCPay plugin.
     *
     * @return array<string, mixed>|null
     */
    public function walletConnectionPayload(Store $store, ?WalletConnection $walletConnection): ?array
    {
        if (($store->wallet_type ?? null) === 'cashu') {
            return [
                'id' => null,
                'type' => 'cashu',
                'status' => 'connected',
                'masked_secret' => null,
                'submitted_at' => $store->created_at,
                'secret_updated_at' => null,
                'submitted_by_user_id' => null,
            ];
        }

        if (! $walletConnection) {
            return null;
        }

        return [
            'id' => $walletConnection->id,
            'type' => $walletConnection->type,
            'status' => $walletConnection->status,
            'configuration_source' => $walletConnection->configuration_source,
            'masked_secret' => $walletConnection->masked_secret,
            'submitted_at' => $walletConnection->created_at,
            'secret_updated_at' => $walletConnection->secret_updated_at,
            'submitted_by_user_id' => $walletConnection->submitted_by_user_id,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function checklistItemsPayload(Store $store): Collection
    {
        if (! $store->checklistItems || $store->checklistItems->count() === 0) {
            return collect([]);
        }

        $definition = StoreChecklistService::getChecklistItems($store->wallet_type ?? '');

        return $store->checklistItems->map(function ($item) use ($definition) {
            $itemDef = $definition[$item->item_key] ?? null;

            return [
                'key' => $item->item_key,
                'description' => $itemDef['description'] ?? $item->item_key,
                'link' => $itemDef['link'] ?? null,
                'completed_at' => $item->completed_at,
                'is_completed' => $item->isCompleted(),
            ];
        })->values();
    }
}
