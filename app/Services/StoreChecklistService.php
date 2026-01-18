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
     * Note: All configuration should be done via UZOL21 UI, not BTCPay UI.
     * Links to BTCPay UI are removed as merchants should not access BTCPay directly.
     */
    public static function getAllChecklistItems(): array
    {
        return [
            'blink' => [
                'connect_wallet' => [
                    'key' => 'connect_wallet',
                    'description' => 'Connect Blink wallet via Wallet Connection settings',
                    'link' => null, // No direct BTCPay UI links - configure via UZOL21
                    'order' => 1,
                ],
                'enable_lightning' => [
                    'key' => 'enable_lightning',
                    'description' => 'Confirm Lightning payments are enabled',
                    'link' => null,
                    'order' => 2,
                ],
                'test_invoice' => [
                    'key' => 'test_invoice',
                    'description' => 'Create and test a test invoice payment',
                    'link' => null,
                    'order' => 3,
                ],
                'set_payout_policy' => [
                    'key' => 'set_payout_policy',
                    'description' => 'Optional: Set payout/withdrawal policy in Blink wallet',
                    'link' => null,
                    'order' => 4,
                    'optional' => true,
                ],
            ],
            'aqua_boltz' => [
                'configure_wallet' => [
                    'key' => 'configure_wallet',
                    'description' => 'Configure Aqua wallet connection via Wallet Connection settings',
                    'link' => null,
                    'order' => 1,
                ],
                'enable_boltz_plugin' => [
                    'key' => 'enable_boltz_plugin',
                    'description' => 'Enable Boltz plugin for the store (requires support assistance)',
                    'link' => null,
                    'order' => 2,
                ],
                'connect_aqua_wallet' => [
                    'key' => 'connect_aqua_wallet',
                    'description' => 'Connect Aqua wallet via Boltz (requires support assistance)',
                    'link' => null,
                    'order' => 3,
                ],
                'verify_swap_routing' => [
                    'key' => 'verify_swap_routing',
                    'description' => 'Verify swap routing works correctly',
                    'link' => null,
                    'order' => 4,
                ],
                'test_lightning_invoice' => [
                    'key' => 'test_lightning_invoice',
                    'description' => 'Create and test a Lightning invoice payment',
                    'link' => null,
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








