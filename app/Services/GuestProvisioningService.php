<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\BtcPay\WebhookService;
use App\Services\StoreChecklistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestProvisioningService
{
    public function __construct(
        protected UserService $userService,
        protected StoreService $storeService,
        protected WebhookService $webhookService
    ) {}

    public function attachRecoveryKeyToGuest(User $user, string $recoveryPkHex): User
    {
        if (! ($user->is_guest ?? false)
            || ! Schema::hasColumn('users', 'guest_recovery_public_key')
            || ! empty($user->guest_recovery_public_key)) {
            return $user;
        }

        if (User::where('guest_recovery_public_key', $recoveryPkHex)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'recovery_public_key' => ['This recovery key is already in use.'],
            ]);
        }

        $user->update([
            'guest_recovery_public_key' => $recoveryPkHex,
            'guest_recovery_enrolled_at' => now(),
        ]);

        return $user->fresh();
    }

    public function resolvePrimaryStoreId(User $user): ?string
    {
        return Store::where('user_id', $user->id)->value('id');
    }

    /**
     * @return array{0: User, 1: Store}
     */
    public function provisionGuest(?string $recoveryPkHex = null): array
    {
        $guestToken = strtolower((string) Str::ulid());
        $guestEmailDomain = (string) config('services.auth.guest_email_domain', 'guest.satflux.local');
        $guestEmail = "guest+{$guestToken}@{$guestEmailDomain}";
        $guestPassword = Str::random(48);
        $defaultStoreName = 'My Store';

        $btcpayUserId = null;
        $btcpayStoreId = null;
        $btcpayApiKey = null;
        $createdPerUserApiKey = null;
        $webhookData = null;

        try {
            $btcpayUser = $this->userService->createUser([
                'email' => $guestEmail,
                'password' => Str::random(32),
                'isAdministrator' => false,
                'sendInvitationEmail' => false,
            ]);
            $btcpayUserId = $btcpayUser['id'] ?? $btcpayUser['userId'] ?? null;
            if (! $btcpayUserId) {
                throw new \RuntimeException('BTCPay user ID missing after guest user creation.');
            }

            try {
                $apiKeyData = $this->userService->createApiKey(
                    $btcpayUserId,
                    [],
                    [],
                    'satflux.io Guest API Key - '.$guestEmail
                );
                $createdPerUserApiKey = $apiKeyData['apiKey'] ?? null;
            } catch (\Throwable $apiKeyError) {
                Log::warning('Guest BTCPay user API key creation failed, using server key for provisioning only', [
                    'btcpay_user_id' => $btcpayUserId,
                    'guest_email' => $guestEmail,
                    'error' => $apiKeyError->getMessage(),
                ]);
            }

            $btcpayApiKey = $createdPerUserApiKey ?: null;
            $btcpayStore = $this->storeService->createStore([
                'name' => $defaultStoreName,
                'defaultCurrency' => 'EUR',
                'timeZone' => 'Europe/Vienna',
                'anyoneCanCreateInvoice' => false,
                'showRecommendedFee' => true,
                'recommendedFeeBlockTarget' => 1,
                'preferredExchange' => 'kraken',
            ], $btcpayApiKey);
            $btcpayStoreId = $btcpayStore['id'] ?? $btcpayStore['storeId'] ?? null;
            if (! $btcpayStoreId) {
                throw new \RuntimeException('BTCPay store ID missing after guest store creation.');
            }

            Cache::forget("btcpay:store:{$btcpayStoreId}:server");
            if ($btcpayApiKey) {
                Cache::forget("btcpay:store:{$btcpayStoreId}:".md5($btcpayApiKey));
            }

            try {
                $this->storeService->addUserToStore($btcpayStoreId, $btcpayUserId, 'Owner');
            } catch (\Throwable $e) {
                Log::warning('Failed to add guest BTCPay user to guest store', [
                    'btcpay_store_id' => $btcpayStoreId,
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $webhookData = $this->webhookService->replacePanelWebhookForStore($btcpayStoreId, $btcpayApiKey);
            } catch (\Throwable $e) {
                Log::warning('Guest store webhook provisioning failed', [
                    'btcpay_store_id' => $btcpayStoreId,
                    'error' => $e->getMessage(),
                ]);
            }

            [$user, $store] = DB::transaction(function () use (
                $guestEmail,
                $guestPassword,
                $recoveryPkHex,
                $btcpayUserId,
                $btcpayStoreId,
                $defaultStoreName,
                $createdPerUserApiKey,
                $webhookData
            ) {
                $userData = [
                    'name' => 'Guest',
                    'email' => $guestEmail,
                    'password' => Hash::make($guestPassword),
                    'role' => 'free',
                    'email_verified_at' => now(),
                    'btcpay_user_id' => $btcpayUserId,
                ];

                if (Schema::hasColumn('users', 'is_guest')) {
                    $userData['is_guest'] = true;
                }

                if ($createdPerUserApiKey) {
                    $userData['btcpay_api_key'] = $createdPerUserApiKey;
                }

                if ($recoveryPkHex && Schema::hasColumn('users', 'guest_recovery_public_key')) {
                    if (User::where('guest_recovery_public_key', $recoveryPkHex)->exists()) {
                        throw ValidationException::withMessages([
                            'recovery_public_key' => ['This recovery key is already in use.'],
                        ]);
                    }
                    $userData['guest_recovery_public_key'] = $recoveryPkHex;
                    $userData['guest_recovery_enrolled_at'] = now();
                }

                $user = User::create($userData);

                $store = Store::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'btcpay_store_id' => $btcpayStoreId,
                    'name' => $defaultStoreName,
                    'default_currency' => 'EUR',
                    'timezone' => 'Europe/Vienna',
                    'preferred_exchange' => 'kraken',
                    'wallet_type' => null,
                    'btcpay_webhook_id' => $webhookData['id'] ?? null,
                    'webhook_secret' => $webhookData['secret'] ?? null,
                ]);

                StoreChecklistService::ensureChecklistInitialized($store);

                return [$user->fresh(), $store->fresh()];
            });

            return [$user, $store];
        } catch (ValidationException $e) {
            $this->cleanupBtcPayResources($btcpayStoreId, $btcpayUserId, $createdPerUserApiKey);
            throw $e;
        } catch (\Throwable $e) {
            $this->cleanupBtcPayResources($btcpayStoreId, $btcpayUserId, $createdPerUserApiKey);
            throw $e;
        }
    }

    private function cleanupBtcPayResources(?string $btcpayStoreId, ?string $btcpayUserId, ?string $createdPerUserApiKey): void
    {
        if ($btcpayStoreId) {
            try {
                $this->storeService->deleteStore($btcpayStoreId);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest provisioning cleanup: failed to delete BTCPay store', [
                    'btcpay_store_id' => $btcpayStoreId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }

        if ($btcpayUserId && $createdPerUserApiKey) {
            try {
                $this->userService->deleteUserApiKey($btcpayUserId, $createdPerUserApiKey);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest provisioning cleanup: failed to delete BTCPay user API key', [
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }

        if ($btcpayUserId) {
            try {
                $this->userService->deleteUser($btcpayUserId);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest provisioning cleanup: failed to delete BTCPay user', [
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }
    }
}

