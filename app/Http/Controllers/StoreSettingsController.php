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
        
        // Get store data from BTCPay (cached)
        $btcpayStore = $this->storeService->getStore($store->btcpay_store_id);

        return response()->json([
            'data' => [
                'name' => $store->name,
                'default_currency' => $btcpayStore['defaultCurrency'] ?? null,
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

        // Update in BTCPay
        $this->storeService->updateStore($store->btcpay_store_id, [
            'name' => $request->name,
            'defaultCurrency' => $request->default_currency,
        ]);

        // Update local record
        $store->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'data' => [
                'name' => $store->fresh()->name,
                'default_currency' => $request->default_currency,
            ],
            'message' => 'Store settings updated successfully',
        ]);
    }
}

