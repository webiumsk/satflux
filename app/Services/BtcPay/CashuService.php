<?php

namespace App\Services\BtcPay;

use App\Exceptions\CashuWalletSafetyException;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

class CashuService
{
    public function __construct(protected BtcPayClient $client) {}

    public function getSettings(string $storeId, string $apiKey): array
    {
        return $this->client->withUserKey($apiKey, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/plugins/cashumelt/settings");
        });
    }

    public function saveSettings(string $storeId, array $data, string $apiKey): array
    {
        return $this->client->withUserKey($apiKey, function () use ($storeId, $data) {
            // $data = ['mintUrl' => ..., 'lightningAddress' => ..., 'enabled' => true]
            return $this->client->put("/api/v1/stores/{$storeId}/plugins/cashumelt/settings", $data);
        });
    }

    public function listPayments(string $storeId, string $apiKey, array $params = []): array
    {
        return $this->client->withUserKey($apiKey, function () use ($storeId, $params) {
            // $params = ['limit' => ..., 'offset' => ..., 'settlementState' => 'SETTLED'|'PENDING'|'FAILED']
            return $this->client->get("/api/v1/stores/{$storeId}/plugins/cashumelt/payments", $params);
        });
    }

    public function retryPayment(string $storeId, string $quoteId, string $apiKey): array
    {
        return $this->client->withUserKey($apiKey, function () use ($storeId, $quoteId) {
            return $this->client->post(
                "/api/v1/stores/{$storeId}/plugins/cashumelt/payments/{$quoteId}/retry",
                []
            );
        });
    }

    public function confirmOriginalSettings(string $storeId, string $quoteId, array $data, string $apiKey): array
    {
        return $this->client->withUserKey($apiKey, function () use ($storeId, $quoteId, $data) {
            return $this->client->post(
                '/api/v1/stores/'.rawurlencode($storeId).'/plugins/cashumelt/payments/'.rawurlencode($quoteId).'/confirm-original',
                $data
            );
        });
    }

    /** Read-only evidence for presentation; writes always perform their own fresh check. */
    public function getWalletSafety(string $storeId, string $apiKey): array
    {
        try {
            $result = $this->client->withUserKey($apiKey,
                fn () => $this->client->get("/api/v1/stores/{$storeId}/plugins/cashumelt/safety"));

            return $this->formatWalletSafety($result);
        } catch (\Throwable) {
            return $this->formatWalletSafety([]);
        }
    }

    /** The provider boolean alone controls status; counters are record counts, not amounts. */
    private function formatWalletSafety(array $result): array
    {
        $status = match ($result['safeToChangeWallet'] ?? null) {
            true => 'safe',
            false => 'blocked',
            default => 'unavailable',
        };
        $count = static fn (mixed $value): ?int => is_int($value) && $value >= 0 && $value <= 2147483647 ? $value : null;

        return [
            'status' => $status,
            'unresolved_payments' => $count($result['unresolvedPayments'] ?? null),
            'unresolved_change' => $count($result['unresolvedChange'] ?? null),
        ];
    }

    public function assertNoUnresolvedSettlements(string $storeId, string $apiKey): void
    {
        try {
            $result = $this->client->withUserKey($apiKey,
                fn () => $this->client->get("/api/v1/stores/{$storeId}/plugins/cashumelt/safety"));
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 404) {
                throw new CashuWalletSafetyException('cashu_safety_unavailable', 'Update CashuMelt to version 1.4.0.0 or later before changing this wallet.');
            }
            throw new CashuWalletSafetyException('cashu_safety_unavailable', 'Cashu wallet safety could not be verified. The wallet was not changed.');
        } catch (\Throwable) {
            throw new CashuWalletSafetyException('cashu_safety_unavailable', 'Cashu wallet safety could not be verified. The wallet was not changed.');
        }
        if (($result['safeToChangeWallet'] ?? null) === false) {
            throw new CashuWalletSafetyException('cashu_outstanding_payments', 'Outstanding Cashu settlements or change must be recovered before changing this wallet.', $this->formatWalletSafety($result));
        }
        if (($result['safeToChangeWallet'] ?? null) !== true) {
            throw new CashuWalletSafetyException('cashu_safety_unavailable', 'Cashu wallet safety could not be verified. The wallet was not changed.', $this->formatWalletSafety($result));
        }
    }

    /**
     * Best-effort: remove Cashu checkout payment method from the store (Greenfield DELETE).
     * Disabling the CashuMelt plugin alone is not enough: invoices still try CASHU and fail with
     * "CashuMelt is not configured" if the method stays enabled on the store.
     *
     * @param  string[]  $paymentMethodIds  BTCPay log lines use "CASHU:"; plugin may register variants.
     */
    public function tryRemoveCashuCheckoutPaymentMethods(string $storeId, string $userApiKey, array $paymentMethodIds = ['CASHU', 'CASHUMELT']): void
    {
        $this->client->withUserKey($userApiKey, function () use ($storeId, $paymentMethodIds) {
            foreach ($paymentMethodIds as $paymentMethodId) {
                try {
                    $this->client->delete('/api/v1/stores/'.$storeId.'/payment-methods/'.$paymentMethodId);
                } catch (BtcPayException $e) {
                    if ($e->getStatusCode() === 404) {
                        continue;
                    }

                    Log::warning('BTCPay DELETE Cashu checkout payment method failed', [
                        'btcpay_store_id' => $storeId,
                        'payment_method_id' => $paymentMethodId,
                        'status' => $e->getStatusCode(),
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Best-effort: set CashuMelt plugin enabled=false at BTCPay so checkout does not offer Cashu
     * when the store uses Blink/Aqua in Satflux. No-op if settings cannot be read or already disabled.
     */
    public function tryDisableAtBtcPay(string $btcpayStoreId, string $apiKey): void
    {
        try {
            $current = $this->getSettings($btcpayStoreId, $apiKey);
        } catch (\Throwable $e) {
            Log::info('CashuMelt tryDisable skipped (could not load plugin settings)', [
                'btcpay_store_id' => $btcpayStoreId,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! ($current['enabled'] ?? true)) {
            return;
        }

        $mint = trim((string) ($current['mintUrl'] ?? ''));
        $ln = trim((string) ($current['lightningAddress'] ?? ''));
        if ($mint === '' || $ln === '') {
            Log::warning('CashuMelt tryDisable skipped (mint or Lightning address missing in BTCPay response)', [
                'btcpay_store_id' => $btcpayStoreId,
            ]);

            return;
        }

        try {
            $this->saveSettings($btcpayStoreId, [
                'mintUrl' => $mint,
                'lightningAddress' => $ln,
                'enabled' => false,
            ], $apiKey);
        } catch (\Throwable $e) {
            Log::error('CashuMelt tryDisable PUT failed', [
                'btcpay_store_id' => $btcpayStoreId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
