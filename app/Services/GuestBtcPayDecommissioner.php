<?php

namespace App\Services;

use App\Models\User;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use Illuminate\Support\Facades\Log;

/**
 * Removes guest-related resources from BTCPay (stores, merchant API key, BTCPay user).
 * Shared between failed guest provisioning rollback and inactive guest purge.
 */
class GuestBtcPayDecommissioner
{
    public function __construct(
        protected StoreService $storeService,
        protected UserService $userService,
    ) {}

    /**
     * Rollback after failed provisioning (single store, optional merchant API key).
     */
    public function decommissionPartial(?string $btcpayStoreId, ?string $btcpayUserId, ?string $merchantApiKey): void
    {
        if ($btcpayStoreId) {
            try {
                $this->storeService->deleteStore($btcpayStoreId);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest BTCPay decommission: failed to delete store', [
                    'btcpay_store_id' => $btcpayStoreId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }

        if ($btcpayUserId && $merchantApiKey) {
            try {
                $this->userService->deleteUserApiKey($btcpayUserId, $merchantApiKey);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest BTCPay decommission: failed to delete user API key', [
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }

        if ($btcpayUserId) {
            try {
                $this->userService->deleteUser($btcpayUserId);
            } catch (\Throwable $cleanupError) {
                Log::warning('Guest BTCPay decommission: failed to delete BTCPay user', [
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Delete all BTCPay stores for the user, revoke merchant key, delete BTCPay user.
     * Call before deleting the local User row.
     */
    public function decommissionAllForLocalGuestUser(User $user): void
    {
        $stores = $user->relationLoaded('stores')
            ? $user->stores
            : $user->stores()->get();

        foreach ($stores as $store) {
            if (! $store->btcpay_store_id) {
                continue;
            }
            try {
                $this->storeService->deleteStore($store->btcpay_store_id);
            } catch (\Throwable $e) {
                Log::warning('Guest purge: failed to delete BTCPay store', [
                    'local_user_id' => $user->id,
                    'btcpay_store_id' => $store->btcpay_store_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $btcpayUserId = $user->btcpay_user_id;
        $apiKey = $user->btcpay_api_key;

        if ($btcpayUserId && $apiKey) {
            try {
                $this->userService->deleteUserApiKey($btcpayUserId, $apiKey);
            } catch (\Throwable $e) {
                Log::warning('Guest purge: failed to delete BTCPay user API key', [
                    'local_user_id' => $user->id,
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($btcpayUserId) {
            try {
                $this->userService->deleteUser($btcpayUserId);
            } catch (\Throwable $e) {
                Log::warning('Guest purge: failed to delete BTCPay user', [
                    'local_user_id' => $user->id,
                    'btcpay_user_id' => $btcpayUserId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
