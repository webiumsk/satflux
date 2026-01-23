<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\LightningAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LightningAddressController extends Controller
{
    protected LightningAddressService $lightningAddressService;

    public function __construct(LightningAddressService $lightningAddressService)
    {
        $this->lightningAddressService = $lightningAddressService;
    }

    /**
     * List all lightning addresses for a store.
     */
    public function index(Request $request, Store $store)
    {
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $addresses = $this->lightningAddressService->listAddresses(
                $store->btcpay_store_id,
                $userApiKey
            );

            $addressList = $addresses ?? [];
            $currentCount = is_array($addressList) ? count($addressList) : 0;
            $maxAddresses = $store->user->getMaxLightningAddresses();

            return response()->json([
                'data' => $addressList,
                'limit' => [
                    'max' => $maxAddresses,
                    'current' => $currentCount,
                    'unlimited' => $maxAddresses === null,
                ],
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            $statusCode = $e->getStatusCode() ?: 500;
            $errorMessage = $e->getMessage();
            
            Log::error('Failed to list lightning addresses', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            // Return BTCPay error message directly
            return response()->json([
                'message' => $errorMessage,
            ], $statusCode);
        }
    }

    /**
     * Get a specific lightning address.
     */
    public function show(Request $request, Store $store, string $username)
    {
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $address = $this->lightningAddressService->getAddress(
                $store->btcpay_store_id,
                $username,
                $userApiKey
            );

            return response()->json([
                'data' => $address
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            $statusCode = $e->getStatusCode() ?: 500;
            $errorMessage = $e->getMessage();
            
            if ($statusCode === 404) {
                return response()->json([
                    'message' => 'Lightning address not found',
                ], 404);
            }

            Log::error('Failed to get lightning address', [
                'store_id' => $store->id,
                'username' => $username,
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            // Return BTCPay error message directly
            return response()->json([
                'message' => $errorMessage,
            ], $statusCode);
        }
    }

    /**
     * Create or update a lightning address.
     */
    public function store(Request $request, Store $store, string $username)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'currencyCode' => ['nullable', 'string'],
            'min' => ['nullable', 'string'],
            'max' => ['nullable', 'string'],
            'invoiceMetadata' => ['nullable', 'array'],
        ]);

        // Ensure username in request matches URL parameter
        if ($request->input('username') !== $username) {
            return response()->json([
                'message' => 'Username in request body must match URL parameter',
            ], 422);
        }

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Check if this is a new address (not an update)
        $isNewAddress = false;
        try {
            $this->lightningAddressService->getAddress(
                $store->btcpay_store_id,
                $username,
                $userApiKey
            );
            // Address exists - this is an update, not a new creation
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // Address doesn't exist - this is a new creation
            if ($e->getStatusCode() === 404) {
                $isNewAddress = true;
            }
        }

        // If creating a new address, check the limit
        if ($isNewAddress) {
            $maxAddresses = $store->user->getMaxLightningAddresses();
            
            if ($maxAddresses !== null) {
                // User has a limit - check current count
                try {
                    $existingAddresses = $this->lightningAddressService->listAddresses(
                        $store->btcpay_store_id,
                        $userApiKey
                    );
                    $currentCount = is_array($existingAddresses) ? count($existingAddresses) : 0;
                    
                    if ($currentCount >= $maxAddresses) {
                        $roleName = $store->user->role ?? 'merchant';
                        return response()->json([
                            'message' => "You have reached the maximum number of Lightning Addresses ({$maxAddresses}) for your {$roleName} plan. Please upgrade to add more addresses.",
                        ], 403);
                    }
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    // If we can't list addresses, log but continue - let BTCPay handle it
                    Log::warning('Failed to list addresses for limit check', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            // If maxAddresses is null (unlimited), no need to check
        }

        try {
            // Build request data - BTCPay API expects fields directly in body
            $data = [
                'username' => $request->input('username'),
            ];
            
            // Only include invoiceMetadata if it's provided and not empty
            $invoiceMetadata = $request->input('invoiceMetadata');
            if ($invoiceMetadata !== null && $invoiceMetadata !== []) {
                if (is_array($invoiceMetadata)) {
                    // Check if it's indexed array (which would serialize to JSON array)
                    if (!empty($invoiceMetadata) && array_keys($invoiceMetadata) === range(0, count($invoiceMetadata) - 1)) {
                        // It's an indexed array, BTCPay needs an object - skip it
                        // Don't include invoiceMetadata in request
                    } elseif (!empty($invoiceMetadata)) {
                        // It's already an associative array with content, convert to object
                        $data['invoiceMetadata'] = (object) $invoiceMetadata;
                    }
                    // If empty array, don't include it
                } elseif (is_object($invoiceMetadata)) {
                    // Already an object - only include if not empty
                    if (!empty((array)$invoiceMetadata)) {
                        $data['invoiceMetadata'] = $invoiceMetadata;
                    }
                }
            }
            // If invoiceMetadata is null or empty, don't include it in the request
            
            // Include optional fields - send empty string or null if provided but empty
            // BTCPay accepts empty strings/null for optional fields
            if ($request->has('currencyCode')) {
                $currencyCode = $request->input('currencyCode');
                $data['currencyCode'] = $currencyCode !== null && $currencyCode !== '' ? $currencyCode : null;
            }
            if ($request->has('min')) {
                $min = $request->input('min');
                $data['min'] = $min !== null && $min !== '' ? $min : null;
            }
            if ($request->has('max')) {
                $max = $request->input('max');
                $data['max'] = $max !== null && $max !== '' ? $max : null;
            }
            
            Log::info('Prepared lightning address data', [
                'store_id' => $store->id,
                'username' => $username,
                'data' => $data,
                'invoiceMetadata_type' => isset($data['invoiceMetadata']) ? gettype($data['invoiceMetadata']) : 'not provided',
                'invoiceMetadata_value' => $data['invoiceMetadata'] ?? null,
            ]);

            $address = $this->lightningAddressService->createOrUpdateAddress(
                $store->btcpay_store_id,
                $username,
                $data,
                $userApiKey
            );

            return response()->json([
                'data' => $address,
                'message' => 'Lightning address saved successfully',
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            $statusCode = $e->getStatusCode() ?: 500;
            $errorMessage = $e->getMessage();
            
            Log::error('Failed to save lightning address', [
                'store_id' => $store->id,
                'username' => $username,
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            // Return BTCPay error message directly to user
            return response()->json([
                'message' => $errorMessage, // Use BTCPay's error message directly
            ], $statusCode);
        }
    }

    /**
     * Update a lightning address (alias for store).
     */
    public function update(Request $request, Store $store, string $username)
    {
        return $this->store($request, $store, $username);
    }

    /**
     * Delete a lightning address.
     */
    public function destroy(Request $request, Store $store, string $username)
    {
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $this->lightningAddressService->deleteAddress(
                $store->btcpay_store_id,
                $username,
                $userApiKey
            );

            return response()->json([
                'message' => 'Lightning address deleted successfully',
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            $statusCode = $e->getStatusCode() ?: 500;
            $errorMessage = $e->getMessage();
            
            if ($statusCode === 404) {
                return response()->json([
                    'message' => 'Lightning address not found',
                ], 404);
            }

            Log::error('Failed to delete lightning address', [
                'store_id' => $store->id,
                'username' => $username,
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            // Return BTCPay error message directly
            return response()->json([
                'message' => $errorMessage,
            ], $statusCode);
        }
    }
}

