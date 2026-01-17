<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUpdateRequest;
use App\Services\BtcPay\StoreService;
use Illuminate\Http\Request;

class StoreSettingsController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * Get store settings.
     */
    public function show(Request $request)
    {
        $store = $request->route('store');
        
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        
        // Clear cache before fetching to ensure we get the latest data
        $apiKeyHash = md5($userApiKey);
        $cacheKey = "btcpay:store:{$store->btcpay_store_id}:{$apiKeyHash}";
        \Illuminate\Support\Facades\Cache::forget($cacheKey);
        
        // Get store data from BTCPay using merchant token
        $btcpayStore = $this->storeService->getStore($store->btcpay_store_id, $userApiKey);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Fetching store settings', [
            'store_id' => $store->id,
            'btcpay_store_id' => $store->btcpay_store_id,
            'btcpay_store_keys' => array_keys($btcpayStore),
            'timeZone' => $btcpayStore['timeZone'] ?? 'NOT_FOUND',
            'preferredExchange' => $btcpayStore['preferredExchange'] ?? 'NOT_FOUND',
            'full_btcpay_store' => $btcpayStore, // Full response for debugging
        ]);

        return response()->json([
            'data' => [
                'name' => $store->name,
                'default_currency' => $btcpayStore['defaultCurrency'] ?? null,
                'timezone' => $btcpayStore['timeZone'] ?? $btcpayStore['timezone'] ?? 'UTC', // Try both camelCase and snake_case
                'preferred_exchange' => $btcpayStore['preferredExchange'] ?? $btcpayStore['preferred_exchange'] ?? null, // Try both formats
                'logo_url' => $btcpayStore['logoUrl'] ?? $btcpayStore['logo_url'] ?? $btcpayStore['imageUrl'] ?? $btcpayStore['image_url'] ?? null, // Logo URL from BTCPay
                'btcpay_store_url' => config('services.btcpay.base_url') . '/stores/' . $store->btcpay_store_id,
                // Read-only fields from BTCPay (simplified - actual API may have more)
                'payment_methods' => $btcpayStore['archived'] ?? false ? [] : ['BTC', 'Lightning'], // Simplified
            ],
        ]);
    }

    /**
     * Update store settings (safe fields only).
     */
    public function update(StoreUpdateRequest $request)
    {
        $store = $request->route('store');

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Update in BTCPay using merchant token
        $updateData = [
            'name' => $request->name,
            'defaultCurrency' => $request->default_currency,
            'timeZone' => $request->timezone,
        ];
        
        // Add preferred exchange if provided
        if ($request->filled('preferred_exchange')) {
            $updateData['preferredExchange'] = $request->preferred_exchange;
        } else {
            // If empty, we might want to set it to null/empty to use recommendation
            $updateData['preferredExchange'] = null;
        }
        
        $this->storeService->updateStore($store->btcpay_store_id, $updateData, $userApiKey);

        // Update local record
        $store->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'data' => [
                'name' => $store->fresh()->name,
                'default_currency' => $request->default_currency,
                'timezone' => $request->timezone,
                'preferred_exchange' => $request->preferred_exchange,
            ],
            'message' => 'Store settings updated successfully',
        ]);
    }
}







