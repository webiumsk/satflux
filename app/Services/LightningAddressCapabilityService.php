<?php

namespace App\Services;

use App\Exceptions\LightningAddressCapabilityException;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Cache;

/** Read-only connection decisions; the existing wallet writers retain final authority. */
class LightningAddressCapabilityService
{
    public function __construct(
        private LnAddressLud21Prober $prober,
        private CashuService $cashu,
    ) {}

    /**
     * @return array{lud21: bool, reason: string, outcome: string, code: string, alternatives: list<array{type: string, available: bool, blocked_reason: string|null, mint_url: string|null, requires_consent: bool}>}
     */
    public function evaluate(Store $store, string $address, bool $fresh = false): array
    {
        $probe = $this->prober->probe($address, $fresh);
        $outcome = match ($probe['reason']) {
            'ok' => 'supported',
            'no_verify' => 'unsupported',
            'unreachable' => 'unavailable',
            default => 'invalid',
        };

        return [
            ...$probe,
            'outcome' => $outcome,
            'code' => match ($outcome) {
                'supported' => 'lud21_supported',
                'unsupported' => 'lud21_unsupported',
                'unavailable' => 'lud21_unavailable',
                default => 'lud21_invalid',
            },
            'alternatives' => $outcome === 'unsupported' ? [$this->cashuAlternative($store, $fresh)] : [],
        ];
    }

    /** Save-time checks deliberately bypass the presentation cache before any wallet mutation. */
    public function assertSupported(Store $store, string $address): void
    {
        $decision = $this->evaluate($store, $address, fresh: true);
        if (! $decision['lud21']) {
            throw new LightningAddressCapabilityException($decision);
        }
    }

    /** @return array{type: string, available: bool, blocked_reason: string|null, mint_url: string|null, requires_consent: bool} */
    private function cashuAlternative(Store $store, bool $fresh): array
    {
        $alternative = ['type' => 'cashu', 'available' => false, 'blocked_reason' => null, 'mint_url' => null, 'requires_consent' => true];
        if ($store->wallet_type !== null && ! in_array($store->wallet_type, ['cashu', 'blink', 'blitz', 'flash', 'lnaddress', 'aqua_boltz', 'nwc'], true)) {
            return [...$alternative, 'blocked_reason' => 'wallet_type_unsupported'];
        }
        if (WalletConfigurationAttempt::where('store_id', $store->id)->unresolved()->exists()) {
            return [...$alternative, 'blocked_reason' => 'wallet_reconciliation_required'];
        }
        $merchant = $store->user;
        $key = $merchant instanceof User ? $merchant->btcpay_api_key : null;
        if (! is_string($key) || $key === '' || ! $store->btcpay_store_id) {
            return [...$alternative, 'blocked_reason' => 'merchant_connection_unavailable'];
        }

        try {
            $settings = $this->cashu->getSettings($store->btcpay_store_id, $key);
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() !== 404) {
                return [...$alternative, 'blocked_reason' => 'cashu_settings_unavailable'];
            }
            // Older consumers allow an absent configuration to use the default
            // mint. The separate safety endpoint must still prove plugin support.
            $settings = [];
        } catch (\Throwable) {
            return [...$alternative, 'blocked_reason' => 'cashu_settings_unavailable'];
        }
        // An existing merchant-selected mint takes precedence over the operator's default.
        $mint = $settings['mintUrl'] ?? null;
        if (! is_string($mint) || trim($mint) === '') {
            $mint = config('services.cashu.default_mint_url');
        }
        if (! is_string($mint) || ! $this->validMint($mint)) {
            return [...$alternative, 'blocked_reason' => 'cashu_mint_unavailable'];
        }
        $alternative['mint_url'] = rtrim(trim($mint), '/');
        $safety = $this->cashu->getWalletSafety($store->btcpay_store_id, $key);
        if (($safety['status'] ?? null) !== 'safe') {
            return [...$alternative, 'blocked_reason' => ($safety['status'] ?? null) === 'blocked' ? 'cashu_outstanding_payments' : 'cashu_safety_unavailable'];
        }
        if ($store->wallet_type !== null) {
            try {
                $this->assertAdvisoryWalletCanChange($store, $key, $fresh);
            } catch (\Throwable) {
                return [...$alternative, 'blocked_reason' => 'wallet_change_unavailable'];
            }
        }

        return [...$alternative, 'available' => true];
    }

    /** Briefly reuse successful invoice scans for presentation, never for a save. */
    private function assertAdvisoryWalletCanChange(Store $store, string $merchantKey, bool $fresh): void
    {
        $check = function () use ($store): bool {
            app(WalletConnectionService::class)->assertWalletCanChange($store);

            return true;
        };
        if ($fresh) {
            $check();

            return;
        }
        $fingerprint = hash('sha256', json_encode([
            $store->user_id, $merchantKey, config('services.btcpay.base_url'),
            $store->btcpay_store_id, $store->wallet_type, $store->cashu_fallback_enabled,
            $store->getRawOriginal('updated_at'),
        ], JSON_THROW_ON_ERROR));
        // Exceptions are not cached. Unresolved writes and Cashu safety are
        // independently checked on every request before reaching this advisory.
        Cache::remember('wallet-change-advisory:'.$store->id.':'.$fingerprint, 10, $check);
    }

    private function validMint(string $mint): bool
    {
        $parts = parse_url(trim($mint));

        return filter_var(trim($mint), FILTER_VALIDATE_URL) !== false
            && is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['fragment']) && ! isset($parts['query']);
    }
}
