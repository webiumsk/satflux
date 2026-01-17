<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * Get dashboard data.
     * Stores are loaded from BTCPay API, then merged with local metadata.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get local stores first - these are the source of truth for what stores belong to this user
        $localStores = Store::where('user_id', $user->id)
            ->with(['checklistItems'])
            ->get()
            ->keyBy('btcpay_store_id');
        
        // Try to load stores from BTCPay API if merchant has API key
        $btcpayStores = [];
        try {
            if ($user->btcpay_api_key) {
                // Load stores from BTCPay API using merchant token
                $btcpayStores = $this->storeService->listStores($user->btcpay_api_key);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails, we'll use local stores only
            Log::warning('BTCPay API failed when loading dashboard stores, using local stores only', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        
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
            
            $stores = $localStores->filter(function ($localStore) use ($btcpayStoreIds) {
                // Only include if local store's btcpay_store_id exists in BTCPay API response
                return in_array($localStore->btcpay_store_id, $btcpayStoreIds);
            })->map(function ($localStore) use ($btcpayStores) {
                // Find matching BTCPay store data
                $btcpayStore = collect($btcpayStores)->first(function ($bs) use ($localStore) {
                    $btcpayStoreId = $bs['id'] ?? $bs['storeId'] ?? null;
                    return $btcpayStoreId === $localStore->btcpay_store_id;
                });
                
                return [
                    'id' => $localStore->id,
                    'name' => $btcpayStore['name'] ?? $localStore->name,
                    'wallet_type' => $localStore->wallet_type,
                    'created_at' => $localStore->created_at ?? ($btcpayStore['created'] ?? now()),
                ];
            })->values();
        }
        
        return response()->json([
            'stores' => $stores,
        ]);
    }
}




