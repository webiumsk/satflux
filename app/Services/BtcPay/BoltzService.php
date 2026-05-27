<?php

namespace App\Services\BtcPay;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\WalletConnectionValidator;
use Illuminate\Support\Facades\Log;

/**
 * Boltz BTCPay plugin Greenfield API (v2.3+).
 *
 * @see https://github.com/BoltzExchange/boltz-btcpay-plugin/blob/master/BTCPayServer.Plugins.Boltz/Resources/swagger.boltz.json
 */
class BoltzService
{
    public function __construct(
        protected BtcPayClient $client,
        protected WalletConnectionValidator $validator,
    ) {}

    /**
     * Deterministic wallet name (matches config bot naming in run-config.js).
     */
    public function buildWalletName(Store $store): string
    {
        $slug = strtolower((string) ($store->name ?? 'store'));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 35) ?: 'store';

        $storeIdPart = preg_replace('/[^a-zA-Z0-9]/', '', (string) $store->btcpay_store_id);
        $storeIdPart = substr($storeIdPart, 0, 12) ?: 'store';

        return "boltz-{$slug}-{$storeIdPart}";
    }

    /**
     * Import watch-only L-BTC wallet via descriptor and enable Boltz standalone for the store.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function importDescriptorAndEnableSetup(
        string $btcpayStoreId,
        string $walletName,
        string $coreDescriptor,
        ?string $userApiKey = null
    ): array {
        $descriptor = $this->validator->stripDescriptorChecksum(trim($coreDescriptor));

        if (! $this->validator->validateAquaDescriptor($descriptor)) {
            return [
                'success' => false,
                'message' => 'Descriptor failed local validation before BTCPay import',
            ];
        }

        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            Log::info('Boltz Greenfield: importing L-BTC wallet', [
                'btcpay_store_id' => $btcpayStoreId,
                'wallet_name' => $walletName,
            ]);

            $walletResponse = $this->client->post(
                "/api/v1/stores/{$btcpayStoreId}/boltz/wallets",
                [
                    'name' => $walletName,
                    'currency' => 'LBTC',
                    'coreDescriptor' => $descriptor,
                ]
            );

            Log::info('Boltz Greenfield: enabling setup', [
                'btcpay_store_id' => $btcpayStoreId,
                'wallet_name' => $walletName,
                'wallet_response_keys' => is_array($walletResponse) ? array_keys($walletResponse) : [],
            ]);

            $setupResponse = $this->client->post(
                "/api/v1/stores/{$btcpayStoreId}/boltz/setup",
                [
                    'walletName' => $walletName,
                ]
            );

            return [
                'success' => true,
                'message' => 'Boltz wallet imported and setup enabled',
                'data' => [
                    'wallet' => $walletResponse,
                    'setup' => $setupResponse,
                ],
            ];
        } catch (BtcPayException $e) {
            Log::info('Boltz Greenfield import/setup failed', [
                'btcpay_store_id' => $btcpayStoreId,
                'wallet_name' => $walletName,
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
            ];
        } finally {
            if ($userApiKey && $originalApiKey !== null) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}
