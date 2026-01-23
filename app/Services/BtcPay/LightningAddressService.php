<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

class LightningAddressService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all lightning addresses for a store.
     * 
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional)
     * @return array List of lightning addresses
     */
    public function listAddresses(string $storeId, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $this->client->get("/api/v1/stores/{$storeId}/lightning-addresses");
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Get a specific lightning address.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $username Lightning address username
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Lightning address data
     */
    public function getAddress(string $storeId, string $username, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $this->client->get("/api/v1/stores/{$storeId}/lightning-addresses/{$username}");
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Create or update a lightning address.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $username Lightning address username
     * @param array $data Address data (username, currencyCode, min, max, invoiceMetadata)
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Created/updated lightning address data
     */
    public function createOrUpdateAddress(string $storeId, string $username, array $data, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // BTCPay API uses POST for both create and update
            // The endpoint is: POST /api/v1/stores/{storeId}/lightning-addresses/{username}
            // According to docs: "Add or update store configured lightning address"
            // 
            // Build final data - only include fields that have non-empty values
            // BTCPay expects fields to be either provided with value or not provided at all
            $finalData = [
                'username' => $data['username'],
            ];
            
            // Only include invoiceMetadata if it's provided and not empty
            if (isset($data['invoiceMetadata'])) {
                // Ensure invoiceMetadata is an object, not an array
                if (is_array($data['invoiceMetadata']) && !empty($data['invoiceMetadata']) && array_keys($data['invoiceMetadata']) === range(0, count($data['invoiceMetadata']) - 1)) {
                    // It's an indexed array, BTCPay needs an object - skip it (don't send empty object)
                    // Don't include invoiceMetadata in request
                } elseif (is_array($data['invoiceMetadata']) && empty($data['invoiceMetadata'])) {
                    // Empty array - don't include invoiceMetadata in request
                } elseif (is_object($data['invoiceMetadata']) && empty((array)$data['invoiceMetadata'])) {
                    // Empty object - don't include invoiceMetadata in request
                } else {
                    // Valid non-empty metadata - ensure it's an object
                    if (!is_object($data['invoiceMetadata'])) {
                        $data['invoiceMetadata'] = (object) $data['invoiceMetadata'];
                    }
                    $finalData['invoiceMetadata'] = $data['invoiceMetadata'];
                }
            }
            // If invoiceMetadata is not provided at all, don't include it in the request
            
            // Add optional fields only if they have non-empty values
            foreach (['currencyCode', 'min', 'max'] as $field) {
                if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                    $finalData[$field] = $data[$field];
                }
            }
            
            Log::info('Lightning address create/update request', [
                'store_id' => $storeId,
                'username' => $username,
                'original_data' => $data,
                'final_data' => $finalData,
                'invoiceMetadata_type' => isset($finalData['invoiceMetadata']) ? gettype($finalData['invoiceMetadata']) : 'not provided',
            ]);
            
            return $this->client->post("/api/v1/stores/{$storeId}/lightning-addresses/{$username}", $finalData);
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Delete a lightning address.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $username Lightning address username
     * @param string|null $userApiKey User-level API key (optional)
     * @return bool True if deleted successfully
     */
    public function deleteAddress(string $storeId, string $username, ?string $userApiKey = null): bool
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            $this->client->delete("/api/v1/stores/{$storeId}/lightning-addresses/{$username}");
            return true;
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}

