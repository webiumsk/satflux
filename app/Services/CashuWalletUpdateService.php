<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\LightningService;
use App\Services\BtcPay\StoreService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashuWalletUpdateService
{
    public function __construct(
        protected CashuService $cashu,
        protected LightningService $lightning,
        protected StoreService $stores,
        protected WalletChangeConfirmationGuard $confirmation,
    ) {}

    /** The caller holds wallet-update:{store}; commit the journal before any remote write. */
    public function update(Store $store, User $user, array $settings, bool $switching): array
    {
        abort_unless((string) $store->user_id === (string) $user->id, 403);
        if (WalletConfigurationAttempt::where('store_id', $store->id)->unresolved()->exists()) {
            throw ValidationException::withMessages(['cashu' => 'A previous wallet update needs reconciliation.']);
        }
        $key = $user->getBtcPayApiKeyOrFail();
        $this->cashu->assertNoUnresolvedSettlements((string) $store->btcpay_store_id, $key);
        $settings['mintUrl'] = rtrim(trim($settings['mintUrl']), '/');
        $settings['lightningAddress'] = trim($settings['lightningAddress']);
        $attempt = WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'cashu',
            'encrypted_secret' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)), 'status' => 'applying',
        ]);
        $remoteApplied = false;
        try {
            $updated = $this->cashu->saveSettings($store->btcpay_store_id, $settings, $key);
            $remoteApplied = true;
            if ($settings['enabled'] ?? true) {
                $this->lightning->tryRemoveLightningCheckoutPaymentMethods($store->btcpay_store_id, $key);
            }
            if (! $this->matches($store, $settings, $key)) {
                throw ValidationException::withMessages(['cashu' => 'Cashu settings need reconciliation before this store can accept new invoices.']);
            }
            $this->confirm($store, $user, $attempt, $switching);

            return $updated;
        } catch (\Throwable $e) {
            $definitiveRejection = ! $remoteApplied && $e instanceof BtcPayException
                && $e->getStatusCode() >= 400 && $e->getStatusCode() < 500
                && ! in_array($e->getStatusCode(), [408, 429], true);
            $attempt->update([
                'status' => $definitiveRejection ? 'rejected' : 'uncertain',
                'encrypted_secret' => $definitiveRejection ? null : $attempt->encrypted_secret,
            ]);
            throw $e;
        }
    }

    /** Reads only. A different configuration after a timeout is not a confirmed rejection. */
    public function reconcile(Store $store, User $user, WalletConfigurationAttempt $attempt): bool
    {
        $settings = json_decode(Crypt::decryptString($attempt->encrypted_secret), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($settings) || ! $this->matches($store, $settings, $user->getBtcPayApiKeyOrFail())) {
            return false;
        }
        $this->confirm($store, $user, $attempt, $store->wallet_type !== null && $store->wallet_type !== 'cashu');

        return true;
    }

    private function matches(Store $store, array $settings, string $key): bool
    {
        $actual = $this->cashu->getSettings($store->btcpay_store_id, $key);
        foreach ($settings as $name => $value) {
            if ($value === null && ! array_key_exists($name, $actual)) {
                continue;
            }
            $equal = is_int($value) || is_float($value)
                ? isset($actual[$name]) && is_numeric($actual[$name]) && (float) $actual[$name] === (float) $value
                : array_key_exists($name, $actual) && $actual[$name] === $value;
            if (! $equal) {
                return false;
            }
        }
        $methods = $this->stores->getStorePaymentMethods((string) $store->btcpay_store_id, $key, includeConfig: true);
        $cashu = collect($methods)->first(fn ($method) => in_array($method['paymentMethodId'] ?? null, ['CASHU', 'CASHUMELT'], true));
        if (! is_array($cashu) || ($cashu['enabled'] ?? null) !== ($settings['enabled'] ?? true)) {
            return false;
        }

        return ! ($settings['enabled'] ?? true) || ! collect($methods)->contains(
            fn ($method) => in_array($method['paymentMethodId'] ?? null, ['BTC-LN', 'BTC-LNURL'], true) && ($method['enabled'] ?? false));
    }

    private function confirm(Store $store, User $user, WalletConfigurationAttempt $attempt, bool $switching): void
    {
        DB::transaction(function () use ($store, $user, $attempt, $switching) {
            $store->markAsPureCashu($switching);
            StoreChecklistService::ensureChecklistInitialized($store);
            $attempt->update(['status' => 'confirmed', 'encrypted_secret' => null]);
            if ($switching) {
                $this->confirmation->consumeGrant($store, $user);
            }
        });
    }
}
