<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StoreService
{
    /** Greenfield 409 codes meaning the user is already a member of the store. */
    public const ALREADY_STORE_USER_CODES = ['duplicate-store-user-role', 'already-store-user'];

    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new store in BTCPay Server.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function createStore(array $data, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, fn () => $this->client->post('/api/v1/stores', $data));
    }

    /**
     * Get a store by ID.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function getStore(string $storeId, ?string $userApiKey = null): array
    {
        // Include API key hash in cache key to prevent cross-merchant cache pollution
        $apiKeyHash = $userApiKey ? hash('sha256', $userApiKey) : 'server';
        $cacheKey = "btcpay:store:{$storeId}:{$apiKeyHash}";

        return Cache::remember($cacheKey, 60, fn () => $this->client->withUserKey(
            $userApiKey,
            fn () => $this->client->get("/api/v1/stores/{$storeId}")
        ));
    }

    /**
     * Store-level payment methods (e.g. BTC-CHAIN, BTC-LN) as configured in BTCPay.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function getStorePaymentMethods(string $storeId, ?string $userApiKey = null, bool $includeConfig = false): array
    {
        // includeConfig needs btcpay.store.canmodifystoresettings (the merchant key has it).
        $query = $includeConfig ? ['includeConfig' => 'true'] : [];

        return $this->client->withUserKey(
            $userApiKey,
            fn () => $this->client->get("/api/v1/stores/{$storeId}/payment-methods", $query)
        );
    }

    /**
     * List all stores (from BTCPay - application layer filters by user mapping).
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function listStores(?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, fn () => $this->client->get('/api/v1/stores'));
    }

    /**
     * Update store settings (safe fields only).
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function updateStore(string $storeId, array $data, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId, $data, $userApiKey) {
            $result = $this->client->put("/api/v1/stores/{$storeId}", $data);
            $this->forgetStoreCache($storeId, $userApiKey);

            return $result;
        });
    }

    /**
     * Add a user to a store.
     * Requires server-level API key with store management permissions.
     *
     * Since BTCPay 2.4.4 (PR btcpayserver#7519) this endpoint creates a
     * pending invitation by default, which the merchant would never see -
     * merchants have no BTCPay UI access. `requireInvitation: false` keeps
     * the direct add; it needs `btcpay.server.canmodifyserversettings` on
     * the server key (or the "store owners can add users without an
     * invitation" server policy). Older hosts ignore the field.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $userId  BTCPay user ID
     * @param  string  $role  User role in store (e.g., 'Owner', 'Guest', 'Viewer')
     * @return array Store user data
     *
     * @throws BtcPayException
     */
    public function addUserToStore(string $storeId, string $userId, string $role = 'Owner'): array
    {
        try {
            return $this->client->post("/api/v1/stores/{$storeId}/users", [
                'userId' => $userId,
                'role' => $role,
                'requireInvitation' => false,
            ]);
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 403 && str_contains(strtolower($e->getMessage()), 'invitation')) {
                Log::error('BTCPay refused a direct store user add: the server API key needs btcpay.server.canmodifyserversettings (or enable the "store owners can add users without an invitation" policy)', [
                    'store_id' => $storeId,
                    'user_id' => $userId,
                ]);
                throw $e;
            }

            // Only BTCPay's explicit "already a member" answers are benign:
            // `duplicate-store-user-role` (< 2.4.4) / `already-store-user` (2.4.4+).
            if ($e->getStatusCode() === 409 && in_array($e->getErrorCode(), self::ALREADY_STORE_USER_CODES, true)) {
                Log::info('User already in store, skipping add', [
                    'store_id' => $storeId,
                    'user_id' => $userId,
                    'role' => $role,
                    'error_code' => $e->getErrorCode(),
                ]);

                try {
                    $users = $this->getStoreUsers($storeId);
                } catch (\Exception $fetchE) {
                    // BTCPay already confirmed membership; we just cannot echo the row back.
                    Log::debug('Could not fetch store users to verify existing user', [
                        'store_id' => $storeId,
                        'error' => $fetchE->getMessage(),
                    ]);

                    return [];
                }

                // BTCPay < 2.4.4 returned `userId`, 2.4.4 returns the compact `id` shape.
                $existingUser = collect($users)->first(
                    fn ($u) => is_array($u) && (($u['userId'] ?? null) === $userId || ($u['id'] ?? null) === $userId)
                );
                if ($existingUser) {
                    return $existingUser;
                }

                // BTCPay said "already a member" but does not list the user - do not
                // let provisioning continue on a store the merchant cannot access.
                Log::warning('BTCPay reported an existing store user that is not in the store user list', [
                    'store_id' => $storeId,
                    'user_id' => $userId,
                ]);
                throw $e;
            }
            // Re-throw other errors
            throw $e;
        }
    }

    /**
     * Get all users for a store.
     *
     * BTCPay 2.4.4 returns the compact `{id, email, roleId}` shape; older
     * hosts returned `userId`/`storeRole` with the full user fields.
     *
     * @param  string  $storeId  BTCPay store ID
     * @return array List of store users
     *
     * @throws BtcPayException
     */
    public function getStoreUsers(string $storeId): array
    {
        return $this->client->get("/api/v1/stores/{$storeId}/users");
    }

    /**
     * Remove a user from a store.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $userId  BTCPay user ID
     * @return bool True if successful
     *
     * @throws BtcPayException
     */
    public function removeUserFromStore(string $storeId, string $userId): bool
    {
        $this->client->delete("/api/v1/stores/{$storeId}/users/{$userId}");

        return true;
    }

    /**
     * Delete a store in BTCPay Server (DELETE /api/v1/stores/{storeId}).
     * Must use server-level API key - merchant keys typically lack this permission.
     *
     * @param  string|null  $userApiKey  Optional merchant key: HTTP delete always uses server key; when set, its hash-scoped store cache is cleared too
     */
    public function deleteStore(string $storeId, ?string $userApiKey = null): void
    {
        // Store deletion requires server-level key (merchant keys lack this permission)
        $this->client->delete("/api/v1/stores/{$storeId}");

        $this->forgetStoreCache($storeId, $userApiKey);
    }

    /**
     * Invalidate cached BTCPay store payload (logo and other fields may change outside PUT /stores).
     */
    protected function forgetStoreCache(string $storeId, ?string $userApiKey): void
    {
        $apiKeyHash = $userApiKey ? hash('sha256', $userApiKey) : 'server';
        Cache::forget("btcpay:store:{$storeId}:{$apiKeyHash}");
        Cache::forget("btcpay:store:{$storeId}:server");
    }

    /**
     * Upload a store logo.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function uploadLogo(string $storeId, $file, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId, $file, $userApiKey) {
            $result = $this->client->postMultipart("/api/v1/stores/{$storeId}/logo", $file);
            $this->forgetStoreCache($storeId, $userApiKey);

            return $result;
        });
    }

    /**
     * Delete a store logo.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function deleteLogo(string $storeId, ?string $userApiKey = null): void
    {
        $this->client->withUserKey($userApiKey, function () use ($storeId, $userApiKey) {
            try {
                $this->client->delete("/api/v1/stores/{$storeId}/logo");
            } catch (BtcPayException $e) {
                // No uploaded logo blob (e.g. only external logoUrl) - still clear logoUrl on the store below
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
            }

            // BTCPay may keep logoUrl on the store after DELETE /logo (external URL, fileid reference, or 2.x branding).
            // Partial PUT matches how StoreSettingsController updates BTCPay.
            $this->updateStore($storeId, ['logoUrl' => null], $userApiKey);
        });
    }
}
