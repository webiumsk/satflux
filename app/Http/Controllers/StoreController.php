<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreateRequest;
use App\Models\Store;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    protected StoreService $storeService;
    protected UserService $userService;

    public function __construct(StoreService $storeService, UserService $userService)
    {
        $this->storeService = $storeService;
        $this->userService = $userService;
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
                    'btcpay_store_ids' => is_array($btcpayStores) ? array_map(function($s) {
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
            
            // Get server-level API key (unrestricted - has all permissions)
            $serverApiKey = config('services.btcpay.api_key', env('BTCPAY_API_KEY'));
            if (!$serverApiKey) {
                abort(500, 'Server-level BTCPay API key not configured.');
            }
            
            // Ensure client is using server-level API key
            $btcPayClient = app(\App\Services\BtcPay\BtcPayClient::class);
            $btcPayClient->setApiKey($serverApiKey);
            
            // Create store in BTCPay using server-level API key
            $storeData = [
                'name' => $request->name,
                'defaultCurrency' => $request->default_currency,
                'timeZone' => $request->timezone,
            ];
            
            // Add preferred exchange if provided (even if empty string, set to null for recommendation)
            if ($request->has('preferred_exchange')) {
                $storeData['preferredExchange'] = $request->preferred_exchange ?: null;
            }
            
            Log::info('Creating store in BTCPay', [
                'name' => $request->name,
                'defaultCurrency' => $request->default_currency,
                'timeZone' => $request->timezone,
                'preferredExchange' => $storeData['preferredExchange'] ?? null,
            ]);
            
            $btcpayStore = $this->storeService->createStore($storeData, null); // null = use server-level key (current client state)
            
            Log::info('Store created in BTCPay', [
                'store_id' => $btcpayStore['id'] ?? null,
                'btcpay_store' => $btcpayStore,
            ]);

            $btcpayStoreId = $btcpayStore['id'] ?? $btcpayStore['storeId'] ?? null;
            
            // Clear cache after creating store to ensure fresh data is loaded
            if ($btcpayStoreId) {
                \Illuminate\Support\Facades\Cache::forget("btcpay:store:{$btcpayStoreId}:server");
                // Also clear for merchant key if they have one
                if ($user->btcpay_api_key) {
                    $apiKeyHash = md5($user->btcpay_api_key);
                    \Illuminate\Support\Facades\Cache::forget("btcpay:store:{$btcpayStoreId}:{$apiKeyHash}");
                }
            }

            // Add both merchant and admin as Owners to the store
            if ($btcpayStoreId) {
                // Add merchant as Owner
                if ($user->btcpay_user_id) {
                    try {
                        $this->storeService->addUserToStore($btcpayStoreId, $user->btcpay_user_id, 'Owner');
                        Log::info('Assigned merchant to store after creation', [
                            'merchant_btcpay_user_id' => $user->btcpay_user_id,
                            'store_id' => $btcpayStoreId,
                            'merchant_user_id' => $user->id,
                        ]);
                    } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                        Log::error('Failed to assign merchant to store after creation', [
                            'store_id' => $btcpayStoreId,
                            'merchant_user_id' => $user->id,
                            'merchant_btcpay_user_id' => $user->btcpay_user_id,
                            'error' => $e->getMessage(),
                            'error_type' => get_class($e),
                        ]);
                        // Continue - we'll try to add admin anyway
                    }
                } else {
                    Log::warning('Merchant does not have BTCPay user ID - cannot assign merchant to store', [
                        'store_id' => $btcpayStoreId,
                        'merchant_user_id' => $user->id,
                    ]);
                }
                
                // Add admin as Owner (for support access)
                try {
                    $adminBtcPayUserId = $this->userService->getAdminBtcPayUserId();
                    if (!$adminBtcPayUserId) {
                        Log::error('Could not determine admin BTCPay user ID - admin will not have access to store', [
                            'store_id' => $btcpayStoreId,
                            'merchant_user_id' => $user->id,
                        ]);
                    } else {
                        $this->storeService->addUserToStore($btcpayStoreId, $adminBtcPayUserId, 'Owner');
                        Log::info('Assigned admin to store after creation', [
                            'admin_btcpay_user_id' => $adminBtcPayUserId,
                            'store_id' => $btcpayStoreId,
                            'merchant_user_id' => $user->id,
                        ]);
                    }
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    // Log as error - this is important for support access
                    Log::error('Failed to assign admin to store after creation - admin will not have access to store', [
                        'store_id' => $btcpayStoreId,
                        'merchant_user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e),
                    ]);
                    // Don't fail the request - store is created, but admin assignment failed
                } catch (\Exception $e) {
                    Log::error('Unexpected error when assigning admin to store', [
                        'store_id' => $btcpayStoreId,
                        'merchant_user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Create local store record
            $store = Store::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'btcpay_store_id' => $btcpayStore['id'] ?? $btcpayStore['storeId'],
                'name' => $request->name,
                'wallet_type' => $request->wallet_type,
            ]);

            // Create wallet connection if connection_string is provided
            if ($request->filled('connection_string')) {
                try {
                    $connectionType = $request->wallet_type === 'blink' ? 'blink' : 'aqua_descriptor';
                    $walletConnectionService = app(\App\Services\WalletConnectionService::class);
                    $walletConnectionService->createOrUpdate(
                        $store,
                        $connectionType,
                        $request->connection_string,
                        $request->user()
                    );
                    Log::info('Wallet connection created during store creation', [
                        'store_id' => $store->id,
                        'wallet_type' => $request->wallet_type,
                        'connection_type' => $connectionType,
                    ]);
                } catch (\Exception $e) {
                    // Log error but don't fail store creation
                    Log::error('Failed to create wallet connection during store creation', [
                        'store_id' => $store->id,
                        'wallet_type' => $request->wallet_type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'data' => $this->formatStore($store->load('checklistItems')),
                'message' => 'Store created successfully',
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
     * Never expose btcpay_store_id to frontend.
     */
    protected function formatStoreFromBtcPay(array $btcpayStore, Store $localStore): array
    {
        // Load wallet connection if exists
        $walletConnection = $localStore->walletConnection;
        
        $data = [
            'id' => $localStore->id,
            'name' => $btcpayStore['name'] ?? $localStore->name,
            'wallet_type' => $localStore->wallet_type,
            'created_at' => $localStore->created_at,
            'updated_at' => $localStore->updated_at,
            'checklist_items' => $localStore->checklistItems->map(function ($item) use ($localStore) {
                $definition = StoreChecklistService::getChecklistItems($localStore->wallet_type);
                $itemDef = $definition[$item->item_key] ?? null;
                
                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $itemDef['link'] ?? null,
                    'completed_at' => $item->completed_at,
                    'is_completed' => $item->isCompleted(),
                ];
            })->values(),
            'wallet_connection' => $walletConnection ? [
                'id' => $walletConnection->id,
                'type' => $walletConnection->type,
                'status' => $walletConnection->status,
                'masked_secret' => $walletConnection->masked_secret,
                'submitted_at' => $walletConnection->created_at,
            ] : null,
        ];

        // Add BTCPay-specific fields that are safe to expose
        if (isset($btcpayStore['website'])) {
            $data['website'] = $btcpayStore['website'];
        }
        if (isset($btcpayStore['defaultCurrency'])) {
            $data['default_currency'] = $btcpayStore['defaultCurrency'];
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
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Delete from BTCPay Server using merchant API key
            $userApiKey = $user->getBtcPayApiKeyOrFail();
            $this->storeService->deleteStore($store->btcpay_store_id, $userApiKey);

            // Delete local record (this will cascade delete related records)
            $store->delete();

            Log::info('Store deleted', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => 'Store deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete store', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
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
            return response()->json(['message' => 'Unauthorized'], 403);
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
                'message' => 'Logo uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload store logo', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to upload logo: ' . $e->getMessage()], 500);
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
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $userApiKey = $user->getBtcPayApiKeyOrFail();
            $this->storeService->deleteLogo($store->btcpay_store_id, $userApiKey);

            Log::info('Store logo deleted', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => 'Logo deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete store logo', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to delete logo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Format store for API response (never expose btcpay_store_id).
     */
    protected function formatStore(Store $store): array
    {
        // Load wallet connection if exists
        $walletConnection = $store->walletConnection;
        
        return [
            'id' => $store->id,
            'name' => $store->name,
            'wallet_type' => $store->wallet_type,
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
            'logo_url' => null, // Not available from local DB only (would need BTCPay API)
            'checklist_items' => $store->checklistItems->map(function ($item) use ($store) {
                $definition = StoreChecklistService::getChecklistItems($store->wallet_type);
                $itemDef = $definition[$item->item_key] ?? null;
                
                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $itemDef['link'] ?? null,
                    'completed_at' => $item->completed_at,
                    'is_completed' => $item->isCompleted(),
                ];
            })->values(),
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

