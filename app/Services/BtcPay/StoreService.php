<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Cache;

class StoreService
{
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
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $this->client->post('/api/v1/stores', $data);
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Get a store by ID.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function getStore(string $storeId, ?string $userApiKey = null): array
    {
        // Include API key hash in cache key to prevent cross-merchant cache pollution
        $apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
        $cacheKey = "btcpay:store:{$storeId}:{$apiKeyHash}";

        return Cache::remember($cacheKey, 60, function () use ($storeId, $userApiKey) {
            $originalApiKey = null;
            if ($userApiKey) {
                // Temporarily use user-level API key
                $originalApiKey = $this->client->getApiKey();
                $this->client->setApiKey($userApiKey);
            }

            try {
                return $this->client->get("/api/v1/stores/{$storeId}");
            } finally {
                // Restore original API key if we changed it
                if ($userApiKey && $originalApiKey) {
                    $this->client->setApiKey($originalApiKey);
                }
            }
        });
    }

    /**
     * List all stores (from BTCPay - application layer filters by user mapping).
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function listStores(?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $this->client->get('/api/v1/stores');
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Update store settings (safe fields only).
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function updateStore(string $storeId, array $data, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $result = $this->client->put("/api/v1/stores/{$storeId}", $data);

            // Clear cache for both server and merchant keys (in case both were used)
            $apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
            Cache::forget("btcpay:store:{$storeId}:{$apiKeyHash}");
            Cache::forget("btcpay:store:{$storeId}:server"); // Also clear server cache in case it was used

            return $result;
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Add a user to a store.
     * Requires server-level API key with store management permissions.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $userId BTCPay user ID
     * @param string $role User role in store (e.g., 'Owner', 'Guest', 'Viewer')
     * @return array Store user data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function addUserToStore(string $storeId, string $userId, string $role = 'Owner'): array
    {
        try {
            return $this->client->post("/api/v1/stores/{$storeId}/users", [
                'userId' => $userId,
                'role' => $role,
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If user is already in store (409 Conflict or error message contains "already"), this is OK
            $errorMessage = strtolower($e->getMessage());
            if (
                $e->getCode() === 409 ||
                str_contains($errorMessage, 'already') ||
                str_contains($errorMessage, 'already added') ||
                str_contains($errorMessage, 'already exists')
            ) {
                \Illuminate\Support\Facades\Log::info('User already in store, skipping add', [
                    'store_id' => $storeId,
                    'user_id' => $userId,
                    'role' => $role,
                    'error_code' => $e->getCode(),
                ]);
                // Try to get existing user data and return it
                try {
                    $users = $this->getStoreUsers($storeId);
                    $existingUser = collect($users)->firstWhere('userId', $userId);
                    if ($existingUser) {
                        return $existingUser;
                    }
                } catch (\Exception $fetchE) {
                    // If we can't fetch users, just return empty array
                    \Illuminate\Support\Facades\Log::debug('Could not fetch store users to verify existing user', [
                        'store_id' => $storeId,
                        'error' => $fetchE->getMessage(),
                    ]);
                }
                // Return empty array to indicate success (user already exists)
                return [];
            }
            // Re-throw other errors
            throw $e;
        }
    }

    /**
     * Get all users for a store.
     * 
     * @param string $storeId BTCPay store ID
     * @return array List of store users
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function getStoreUsers(string $storeId): array
    {
        return $this->client->get("/api/v1/stores/{$storeId}/users");
    }

    /**
     * Remove a user from a store.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $userId BTCPay user ID
     * @return bool True if successful
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function removeUserFromStore(string $storeId, string $userId): bool
    {
        $this->client->delete("/api/v1/stores/{$storeId}/users/{$userId}");
        return true;
    }

    /**
     * Delete a store in BTCPay Server.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function deleteStore(string $storeId, ?string $userApiKey = null): void
    {
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // Delete request to BTCPay - this removes merchant from store
            // Note: This doesn't delete the store itself, just removes merchant access
            $this->client->delete("/api/v1/stores/{$storeId}");

            // Clear cache for both server and merchant keys
            $apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
            Cache::forget("btcpay:store:{$storeId}:{$apiKeyHash}");
            Cache::forget("btcpay:store:{$storeId}:server");

            \Illuminate\Support\Facades\Log::info('Merchant removed from BTCPay store', [
                'store_id' => $storeId,
                'api_key_type' => $userApiKey ? 'merchant' : 'server',
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // Re-throw BtcPay exceptions so they can be handled upstream
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions
            throw new \App\Services\BtcPay\Exceptions\BtcPayException(
                "Failed to remove merchant from BTCPay store: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Upload a store logo.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function uploadLogo(string $storeId, $file, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // Use multipart form data for file upload
            // Pass file directly to postMultipart
            return $this->client->postMultipart("/api/v1/stores/{$storeId}/logo", $file);
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Delete a store logo.
     * If a user-level API key is provided, it will be used instead of server-level.
     */
    public function deleteLogo(string $storeId, ?string $userApiKey = null): void
    {
        $originalApiKey = null;
        if ($userApiKey) {
            // Temporarily use user-level API key
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $this->client->delete("/api/v1/stores/{$storeId}/logo");
        } finally {
            // Restore original API key if we changed it
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}






