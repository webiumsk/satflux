<?php

namespace App\Services;

class StoreChecklistService
{
    /**
     * Get checklist items for a wallet type.
     */
    public static function getChecklistItems(string $walletType): array
    {
        $items = self::getAllChecklistItems();
        return $items[$walletType] ?? [];
    }

    /**
     * Get all checklist item definitions.
     */
    public static function getAllChecklistItems(): array
    {
        $btcpayBaseUrl = config('services.btcpay.base_url', env('BTCPAY_BASE_URL'));

        return [
            'blink' => [
                'connect_wallet' => [
                    'key' => 'connect_wallet',
                    'description' => 'Connect Blink wallet in BTCPay Store → Wallet settings',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Wallet',
                    'order' => 1,
                ],
                'enable_lightning' => [
                    'key' => 'enable_lightning',
                    'description' => 'Confirm Lightning payments enabled',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Wallet',
                    'order' => 2,
                ],
                'test_invoice' => [
                    'key' => 'test_invoice',
                    'description' => 'Test invoice payment (link to test invoice)',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Invoices',
                    'order' => 3,
                ],
                'set_payout_policy' => [
                    'key' => 'set_payout_policy',
                    'description' => 'Optional: Set payout/withdrawal policy in Blink',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Wallet',
                    'order' => 4,
                    'optional' => true,
                ],
            ],
            'aqua_boltz' => [
                'open_store' => [
                    'key' => 'open_store',
                    'description' => 'Open store in BTCPay',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}',
                    'order' => 1,
                ],
                'enable_boltz_plugin' => [
                    'key' => 'enable_boltz_plugin',
                    'description' => 'Enable Boltz plugin for the store',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Plugins',
                    'order' => 2,
                ],
                'connect_aqua_wallet' => [
                    'key' => 'connect_aqua_wallet',
                    'description' => 'Connect Aqua wallet via Boltz',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Plugins',
                    'order' => 3,
                ],
                'verify_swap_routing' => [
                    'key' => 'verify_swap_routing',
                    'description' => 'Verify swap routing works',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Plugins',
                    'order' => 4,
                ],
                'test_lightning_invoice' => [
                    'key' => 'test_lightning_invoice',
                    'description' => 'Test Lightning invoice payment',
                    'link' => $btcpayBaseUrl . '/stores/{storeId}/Invoices',
                    'order' => 5,
                ],
            ],
        ];
    }

    /**
     * Initialize checklist items for a store.
     */
    public static function initializeChecklist(string $storeId, string $walletType): void
    {
        $items = self::getChecklistItems($walletType);

        foreach ($items as $item) {
            \App\Models\StoreChecklist::create([
                'store_id' => $storeId,
                'item_key' => $item['key'],
            ]);
        }
    }
}

