<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketEventImageController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * Upload an image for a ticket event (logo/banner).
     * Uses the same mechanism as store logo: upload to BTCPay Server (LocalStorage),
     * then restore the store logo so the event gets a LocalStorage URL without changing the store logo.
     */
    public function upload(Request $request, Store $store)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Same max as store logo (2MB)
        ]);

        $user = $request->user();
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        try {
            $userApiKey = $user->getBtcPayApiKeyOrFail();

            // 1. Get current store logo URL so we can restore it after
            $previousLogoUrl = null;
            try {
                $btcpayStore = $this->storeService->getStore($store->btcpay_store_id, $userApiKey);
                $previousLogoUrl = $btcpayStore['logoUrl'] ?? $btcpayStore['logo_url'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Could not fetch current store logo before event image upload', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 2. Upload to BTCPay (same as store logo) – file is stored in LocalStorage, URL returned
            $result = $this->storeService->uploadLogo(
                $store->btcpay_store_id,
                $request->file('image'),
                $userApiKey
            );

            $url = $result['logoUrl'] ?? $result['logo_url'] ?? null;
            if (empty($url)) {
                return response()->json(['message' => 'Upload succeeded but no URL returned'], 500);
            }

            // 3. Restore previous store logo so the store logo is unchanged
            if ($previousLogoUrl !== null && $previousLogoUrl !== '') {
                try {
                    $this->storeService->updateStore(
                        $store->btcpay_store_id,
                        ['logoUrl' => $previousLogoUrl],
                        $userApiKey
                    );
                } catch (\Throwable $e) {
                    Log::warning('Could not restore store logo after event image upload', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Ticket event image uploaded (BTCPay LocalStorage)', [
                'store_id' => $store->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'image_url' => $url,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload ticket event image', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }
}
