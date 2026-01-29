<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreateRequest;
use App\Models\Store;
use App\Services\BtcPay\LightningService;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    protected StoreService $storeService;
    protected UserService $userService;
    protected LightningService $lightningService;

    public function __construct(StoreService $storeService, UserService $userService, LightningService $lightningService)
    {
        $this->storeService = $storeService;
        $this->userService = $userService;
        $this->lightningService = $lightningService;
    }

    /**
     * List all stores for the authenticated user.
     * Stores are loaded from BTCPay API, then merged with local metadata.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get local stores first - these are the source of truth for what stores belong to this user
        $localStores = Store::where('user_id', $user->id)
            ->with(['checklistItems', 'walletConnection'])
            ->get()
            ->keyBy('btcpay_store_id');

        // Try to load stores from BTCPay API if merchant has API key
        $btcpayStores = [];
        try {
            if ($user->btcpay_api_key) {
                // Load stores from BTCPay API using merchant token
                $btcpayStores = $this->storeService->listStores($user->btcpay_api_key);

                Log::info('BTCPay API returned stores', [
                    'user_id' => $user->id,
                    'btcpay_stores_count' => is_array($btcpayStores) ? count($btcpayStores) : 0,
                    'btcpay_store_ids' => is_array($btcpayStores) ? array_map(function ($s) {
                        return $s['id'] ?? $s['storeId'] ?? null;
                    }, $btcpayStores) : [],
                ]);
            } else {
                Log::info('User does not have BTCPay API key', [
                    'user_id' => $user->id,
                ]);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails, we'll use local stores only
            Log::warning('BTCPay API failed when listing stores', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
        }

        Log::info('Store listing summary', [
            'user_id' => $user->id,
            'local_stores_count' => $localStores->count(),
            'btcpay_stores_count' => count($btcpayStores),
            'local_store_ids' => $localStores->keys()->toArray(),
        ]);

        // Only return stores that exist in BOTH local DB AND BTCPay Server
        // Store must be in local DB (metadata) AND merchant must have access via BTCPay API
        if (empty($btcpayStores)) {
            // If no BTCPay stores returned (no API key or API failed), return empty array
            // Store must exist on BTCPay server to be visible
            $stores = collect([]);
        } else {
            // Filter local stores to only include those that exist in BTCPay API response
            $btcpayStoreIds = collect($btcpayStores)->map(function ($bs) {
                return $bs['id'] ?? $bs['storeId'] ?? null;
            })->filter()->values()->toArray();

            Log::info('Filtering stores', [
                'user_id' => $user->id,
                'btcpay_store_ids' => $btcpayStoreIds,
                'local_stores_keys' => $localStores->keys()->toArray(),
            ]);

            $filteredStores = $localStores->filter(function ($localStore) use ($btcpayStoreIds, $user) {
                $matches = in_array($localStore->btcpay_store_id, $btcpayStoreIds);
                Log::info('Store filter check', [
                    'user_id' => $user->id,
                    'local_store_id' => $localStore->id,
                    'btcpay_store_id' => $localStore->btcpay_store_id,
                    'matches' => $matches,
                ]);
                return $matches;
            });

            Log::info('Filtered stores count', [
                'user_id' => $user->id,
                'filtered_count' => $filteredStores->count(),
            ]);

            $stores = $filteredStores->map(function ($localStore) use ($btcpayStores, $user) {
                // Find matching BTCPay store data
                $btcpayStore = collect($btcpayStores)->first(function ($bs) use ($localStore) {
                    $btcpayStoreId = $bs['id'] ?? $bs['storeId'] ?? null;
                    return $btcpayStoreId === $localStore->btcpay_store_id;
                });

                if (!$btcpayStore) {
                    Log::warning('BTCPay store not found for local store', [
                        'user_id' => $user->id,
                        'local_store_id' => $localStore->id,
                        'btcpay_store_id' => $localStore->btcpay_store_id,
                    ]);
                    return null; // Skip this store
                }

                try {
                    // Merge BTCPay data with local metadata
                    $formatted = $this->formatStoreFromBtcPay($btcpayStore, $localStore);
                    Log::info('Store formatted successfully', [
                        'user_id' => $user->id,
                        'store_id' => $formatted['id'] ?? null,
                        'store_name' => $formatted['name'] ?? null,
                    ]);
                    return $formatted;
                } catch (\Exception $e) {
                    Log::error('Error formatting store', [
                        'user_id' => $user->id,
                        'local_store_id' => $localStore->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return null; // Skip this store
                }
            })->filter()->values(); // Filter out null values

            Log::info('Final stores count', [
                'user_id' => $user->id,
                'final_count' => $stores->count(),
            ]);
        }

        return response()->json(['data' => $stores]);
    }

    /**
     * Create a new store.
     */
    public function store(StoreCreateRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = $request->user();

            // Get server-level API key
            $serverApiKey = config('services.btcpay.api_key', env('BTCPAY_API_KEY'));
            if (!$serverApiKey) {
                abort(500, 'Server-level BTCPay API key not configured.');
            }

            // Ensure client is using server-level API key
            $btcPayClient = app(\App\Services\BtcPay\BtcPayClient::class);
            $btcPayClient->setApiKey($serverApiKey);

            // 1. Create store in BTCPay synchronously (minimal data)
            $storeData = [
                'name' => $request->name,
                'defaultCurrency' => $request->default_currency,
                'timeZone' => $request->timezone,
            ];

            if ($request->has('preferred_exchange')) {
                $storeData['preferredExchange'] = $request->preferred_exchange ?: null;
            }

            $btcpayStore = $this->storeService->createStore($storeData, null);
            $btcpayStoreId = $btcpayStore['id'] ?? $btcpayStore['storeId'] ?? null;

            if (!$btcpayStoreId) {
                abort(500, 'Failed to create store: BTCPay did not return a store ID.');
            }

            // 2. Clear listing cache
            if ($user->btcpay_api_key) {
                \Illuminate\Support\Facades\Cache::forget("btcpay:stores:list:" . md5($user->btcpay_api_key));
            }
            \Illuminate\Support\Facades\Cache::forget("btcpay:stores:list:server");

            // 3. Create local store record synchronously
            $store = Store::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'btcpay_store_id' => $btcpayStoreId,
                'name' => $request->name,
                'default_currency' => $request->default_currency ?? 'EUR',
                'timezone' => $request->timezone ?? 'Europe/Vienna',
                'preferred_exchange' => $request->preferred_exchange ?? 'kraken',
                'wallet_type' => $request->wallet_type,
            ]);

            // 4. Dispatch background job for heavy setup tasks (Owners, Wallet, BTCPay Sync)
            \App\Jobs\SetupStoreWallet::dispatch(
                $store,
                $user,
                $request->input('connection_string')
            );

            return response()->json([
                'data' => $this->formatStore($store),
                'message' => __('messages.store_created') . ' (Setup processing in background)',
            ], 201);
        });
    }

    /**
     * Get a specific store.
     * Store is loaded from BTCPay API, then merged with local metadata.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $store = $request->route('store'); // This is the local Store model from route binding

        try {
            // Load merchant API key from store owner
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();

            // Load store from BTCPay API using merchant token
            $btcpayStore = $this->storeService->getStore($store->btcpay_store_id, $userApiKey);

            // Load local metadata
            $store->load('checklistItems', 'walletConnection');

            // Merge BTCPay data with local metadata
            return response()->json([
                'data' => $this->formatStoreFromBtcPay($btcpayStore, $store)
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails, return store from local DB as fallback
            Log::warning('BTCPay API failed when loading store, using local fallback', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'error' => $e->getMessage(),
            ]);

            $store->load('checklistItems');
            return response()->json(['data' => $this->formatStore($store)]);
        }
    }

    /**
     * Format store from BTCPay data merged with local Store model.
     * Note: btcpay_store_id is included for Pay Button generation.
     */
    protected function formatStoreFromBtcPay(array $btcpayStore, Store $localStore): array
    {
        // Load wallet connection if exists
        $walletConnection = $localStore->walletConnection;

        $data = [
            'id' => $localStore->id,
            'name' => $btcpayStore['name'] ?? $localStore->name,
            'default_currency' => $localStore->default_currency ?? ($btcpayStore['defaultCurrency'] ?? 'EUR'),
            'timezone' => $localStore->timezone ?? ($btcpayStore['timeZone'] ?? 'Europe/Vienna'),
            'preferred_exchange' => $localStore->preferred_exchange ?? ($btcpayStore['preferredExchange'] ?? 'kraken'),
            'wallet_type' => $localStore->wallet_type,
            'created_at' => $localStore->created_at,
            'updated_at' => $localStore->updated_at,
            'checklist_items' => ($localStore->checklistItems && $localStore->checklistItems->count() > 0) ? $localStore->checklistItems->map(function ($item) use ($localStore) {
                $definition = StoreChecklistService::getChecklistItems($localStore->wallet_type ?? 'blink');
                $itemDef = $definition[$item->item_key] ?? null;

                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $itemDef['link'] ?? null,
                    'completed_at' => $item->completed_at,
                    'is_completed' => $item->isCompleted(),
                ];
            })->values() : collect([]),
            'wallet_connection' => $walletConnection ? [
                'id' => $walletConnection->id,
                'type' => $walletConnection->type,
                'status' => $walletConnection->status,
                'masked_secret' => $walletConnection->masked_secret,
                'submitted_at' => $walletConnection->created_at,
            ] : null,
        ];

        // Use local store values first, fallback to BTCPay values
        if (!isset($data['default_currency'])) {
            $data['default_currency'] = $localStore->default_currency ?? ($btcpayStore['defaultCurrency'] ?? 'EUR');
        }
        if (!isset($data['timezone'])) {
            $data['timezone'] = $localStore->timezone ?? ($btcpayStore['timeZone'] ?? 'Europe/Vienna');
        }
        if (!isset($data['preferred_exchange'])) {
            $data['preferred_exchange'] = $localStore->preferred_exchange ?? ($btcpayStore['preferredExchange'] ?? 'kraken');
        }

        // Add btcpay_store_id for Pay Button generation
        $data['btcpay_store_id'] = $localStore->btcpay_store_id;

        // Add BTCPay-specific fields that are safe to expose
        if (isset($btcpayStore['website'])) {
            $data['website'] = $btcpayStore['website'];
        }
        if (isset($btcpayStore['archived'])) {
            $data['archived'] = $btcpayStore['archived'];
        }
        // Add logo URL from BTCPay
        if (isset($btcpayStore['logoUrl'])) {
            $data['logo_url'] = $btcpayStore['logoUrl'];
        } elseif (isset($btcpayStore['logo_url'])) {
            $data['logo_url'] = $btcpayStore['logo_url'];
        }

        return $data;
    }

    /**
     * Delete a store.
     */
    public function destroy(Request $request, Store $store)
    {
        $user = $request->user();

        // Ensure user owns the store
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        $btcpayStoreId = $store->btcpay_store_id;
        $localStoreId = $store->id;

        try {
            // Delete merchant from BTCPay store (removes merchant as owner)
            // Note: This removes the merchant from the store but doesn't delete the store itself
            // The store will remain in BTCPay with admin access only
            try {
                $userApiKey = $user->getBtcPayApiKeyOrFail();
                Log::info('Attempting to remove merchant from BTCPay store', [
                    'store_id' => $localStoreId,
                    'btcpay_store_id' => $btcpayStoreId,
                    'user_id' => $user->id,
                ]);

                // Try DELETE request - this will remove merchant from store
                $this->storeService->deleteStore($btcpayStoreId, $userApiKey);

                Log::info('Merchant removed from BTCPay store', [
                    'store_id' => $localStoreId,
                    'btcpay_store_id' => $btcpayStoreId,
                ]);
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                // If DELETE fails, log but continue - we'll still delete locally
                Log::warning('Failed to remove merchant from BTCPay store', [
                    'store_id' => $localStoreId,
                    'btcpay_store_id' => $btcpayStoreId,
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                ]);
            }

            // Delete local record (this will cascade delete related records)
            $store->delete();

            Log::info('Store deleted from local database', [
                'store_id' => $localStoreId,
                'btcpay_store_id' => $btcpayStoreId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => __('messages.store_deleted'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete store', [
                'store_id' => $localStoreId,
                'btcpay_store_id' => $btcpayStoreId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return response()->json(['message' => 'Failed to delete store: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload store logo.
     */
    public function uploadLogo(Request $request, Store $store)
    {
        $user = $request->user();

        // Ensure user owns the store
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        $request->validate([
            'file' => 'required|image|max:2048', // Max 2MB
        ]);

        try {
            $userApiKey = $user->getBtcPayApiKeyOrFail();
            $result = $this->storeService->uploadLogo($store->btcpay_store_id, $request->file('file'), $userApiKey);

            Log::info('Store logo uploaded', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => __('messages.logo_uploaded'),
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload store logo', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => __('messages.logo_upload_failed', ['error' => $e->getMessage()])], 500);
        }
    }

    /**
     * Delete store logo.
     */
    public function deleteLogo(Request $request, Store $store)
    {
        $user = $request->user();

        // Ensure user owns the store
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        try {
            $userApiKey = $user->getBtcPayApiKeyOrFail();
            $this->storeService->deleteLogo($store->btcpay_store_id, $userApiKey);

            Log::info('Store logo deleted', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => __('messages.logo_deleted')]);
        } catch (\Exception $e) {
            Log::error('Failed to delete store logo', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => __('messages.logo_delete_failed', ['error' => $e->getMessage()])], 500);
        }
    }

    /**
     * Format store for API response.
     * Note: btcpay_store_id is included for Pay Button generation.
     */
    protected function formatStore(Store $store): array
    {
        // Load wallet connection if exists
        $walletConnection = $store->walletConnection;

        return [
            'id' => $store->id,
            'name' => $store->name,
            'btcpay_store_id' => $store->btcpay_store_id,
            'default_currency' => $store->default_currency ?? 'EUR',
            'timezone' => $store->timezone ?? 'Europe/Vienna',
            'preferred_exchange' => $store->preferred_exchange ?? 'kraken',
            'wallet_type' => $store->wallet_type,
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
            'logo_url' => null, // Not available from local DB only (would need BTCPay API)
            'checklist_items' => $store->checklistItems ? $store->checklistItems->map(function ($item) use ($store) {
                $definition = StoreChecklistService::getChecklistItems($store->wallet_type ?? 'blink');
                $itemDef = $definition[$item->item_key] ?? null;

                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $itemDef['link'] ?? null,
                    'completed_at' => $item->completed_at,
                    'is_completed' => $item->isCompleted(),
                ];
            })->values() : collect([]),
            'wallet_connection' => $walletConnection ? [
                'id' => $walletConnection->id,
                'type' => $walletConnection->type,
                'status' => $walletConnection->status,
                'masked_secret' => $walletConnection->masked_secret,
                'submitted_at' => $walletConnection->created_at,
            ] : null,
        ];
    }
}

