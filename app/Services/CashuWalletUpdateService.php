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
        $this->confirmation->assert($store, $user);
        $grantId = $this->confirmation->activeGrant($store, $user)?->id;
        if (WalletConfigurationAttempt::where('store_id', $store->id)->unresolved()->exists()) {
            throw ValidationException::withMessages(['cashu' => 'A previous wallet update needs reconciliation.']);
        }
        $key = $user->getBtcPayApiKeyOrFail();
        $settings['mintUrl'] = rtrim(trim($settings['mintUrl']), '/');
        $settings['lightningAddress'] = trim($settings['lightningAddress']);
        // A real no-op is a read only: do not create a journal, consume the code,
        // touch local state, or ask the plugin to rewrite a wallet holding ecash.
        $current = null;
        $unchanged = false;
        if (! $switching && $store->wallet_type === 'cashu') {
            try {
                $unchanged = $this->matches($store, $settings, $key, $current);
            } catch (BtcPayException $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
            }
        }
        if ($unchanged) {
            return $current ?? [];
        }
        $enableOnlySettings = ! $switching && $store->wallet_type === 'cashu'
            ? $this->enableOnlySettings($settings, $current, (string) $store->btcpay_store_id) : null;
        $enableOnly = $enableOnlySettings !== null;
        if ($enableOnly) {
            $settings = $enableOnlySettings;
        } else {
            $this->cashu->assertNoUnresolvedSettlements((string) $store->btcpay_store_id, $key);
            if ($store->wallet_type !== null) {
                app(WalletConnectionService::class)->assertWalletCanChange($store);
            }
        }
        $attempt = WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'cashu',
            'encrypted_secret' => Crypt::encryptString(json_encode(['settings' => $settings, 'confirmation_grant_id' => $grantId, 'enabled_only' => $enableOnly], JSON_THROW_ON_ERROR)), 'status' => 'applying',
        ]);
        $remoteApplied = false;
        try {
            $updated = $this->cashu->saveSettings($store->btcpay_store_id, $settings, $key);
            $remoteApplied = true;
            if (($settings['enabled'] ?? true) && ! $enableOnly) {
                $this->lightning->tryRemoveLightningCheckoutPaymentMethods($store->btcpay_store_id, $key);
            }
            if (! $this->matches($store, $settings, $key, preserveLightning: $enableOnly)) {
                throw ValidationException::withMessages(['cashu' => 'Cashu settings need reconciliation before this store can accept new invoices.']);
            }
            $this->confirm($store, $user, $attempt, $switching, $grantId);

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
        $saved = json_decode(Crypt::decryptString($attempt->encrypted_secret), true, flags: JSON_THROW_ON_ERROR);
        $settings = is_array($saved['settings'] ?? null) ? $saved['settings'] : $saved;
        $grantId = is_array($saved['settings'] ?? null) && is_string($saved['confirmation_grant_id'] ?? null)
            ? $saved['confirmation_grant_id'] : null;
        $enableOnly = ($saved['enabled_only'] ?? false) === true;
        if (($enableOnly && $store->wallet_type !== 'cashu') || ! is_array($settings)
            || ! $this->matches($store, $settings, $user->getBtcPayApiKeyOrFail(), preserveLightning: $enableOnly)) {
            return false;
        }
        $this->confirm($store, $user, $attempt, $store->wallet_type !== null && $store->wallet_type !== 'cashu', $grantId);

        return true;
    }

    /** @param-out array $actual */
    private function matches(Store $store, array $settings, string $key, ?array &$actual = null, bool $preserveLightning = false): bool
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

        return $preserveLightning || ! ($settings['enabled'] ?? true) || ! collect($methods)->contains(
            fn ($method) => in_array($method['paymentMethodId'] ?? null, ['BTC-LN', 'BTC-LNURL'], true) && ($method['enabled'] ?? false));
    }

    /** Restore availability only; the provider still checks the target under its exclusive lease. */
    private function enableOnlySettings(array $requested, ?array $current, string $storeId): ?array
    {
        $fields = ['mintUrl', 'unit', 'lightningAddress', 'trustedMintUrls', 'maxMeltFeeReserveSats', 'maxMeltFeeReservePercentOfMinted'];
        if (($current['storeId'] ?? null) !== $storeId
            || ($current['enabled'] ?? null) !== false || ($requested['enabled'] ?? null) !== true
            || array_diff(array_keys($requested), [...$fields, 'enabled']) !== []) {
            return null;
        }
        $effective = ['enabled' => true];
        foreach ($fields as $name) {
            if (! array_key_exists($name, $current)) {
                return null;
            }
            if ($name === 'maxMeltFeeReservePercentOfMinted' && $current[$name] !== null
                && ! (is_int($current[$name]) || is_float($current[$name]))) {
                return null;
            }
            $effective[$name] = array_key_exists($name, $requested) ? $requested[$name] : $current[$name];
            $same = $effective[$name] === $current[$name];
            // Percentage caps are bounded; integer and decimal JSON forms are equivalent.
            if ($name === 'maxMeltFeeReservePercentOfMinted' && is_numeric($effective[$name]) && is_numeric($current[$name])) {
                $same = (float) $effective[$name] === (float) $current[$name];
            }
            if (! $same) {
                return null;
            }
        }
        if (! is_string($effective['mintUrl']) || ! str_starts_with($effective['mintUrl'], 'https://')
            || ! in_array($effective['unit'], ['sat', 'usd'], true)
            || ! is_string($effective['lightningAddress']) || ! preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/', $effective['lightningAddress'])
            || ! (is_null($effective['trustedMintUrls']) || is_string($effective['trustedMintUrls']))
            || ! (is_null($effective['maxMeltFeeReserveSats']) || (is_int($effective['maxMeltFeeReserveSats']) && $effective['maxMeltFeeReserveSats'] >= 0))
            || ! (is_null($effective['maxMeltFeeReservePercentOfMinted']) || ((is_int($effective['maxMeltFeeReservePercentOfMinted']) || is_float($effective['maxMeltFeeReservePercentOfMinted']))
                && is_finite((float) $effective['maxMeltFeeReservePercentOfMinted']) && $effective['maxMeltFeeReservePercentOfMinted'] >= 0 && $effective['maxMeltFeeReservePercentOfMinted'] <= 100))) {
            return null;
        }

        return $effective;
    }

    private function confirm(Store $store, User $user, WalletConfigurationAttempt $attempt, bool $switching, ?string $grantId): void
    {
        DB::transaction(function () use ($store, $user, $attempt, $switching, $grantId) {
            $store->markAsPureCashu($switching);
            StoreChecklistService::ensureChecklistInitialized($store);
            $attempt->update(['status' => 'confirmed', 'encrypted_secret' => null]);
            $this->confirmation->consumeCapturedGrant($store, $user, $grantId);
        });
    }
}
