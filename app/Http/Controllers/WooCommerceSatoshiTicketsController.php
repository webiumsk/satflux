<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * WooCommerce Satoshi Tickets plugin - Connect flow.
 * GET /woocommerce/satoshi-tickets/connect?return_url={encoded_settings_url}
 *
 * Flow:
 * 1. WP plugin redirects user to this URL with return_url
 * 2. User must be logged in (redirect to login if not)
 * 3. If user has stores: redirect to return_url with satflux_return=1, btcpay_url, api_key, store_id
 * 4. If multiple stores: show store picker, then redirect
 */
class WooCommerceSatoshiTicketsController extends Controller
{
    /**
     * Connect WooCommerce Satoshi Tickets - OAuth-like redirect flow.
     */
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'return_url' => ['required', 'url', 'max:2000'],
        ], [
            'return_url.required' => 'return_url is required',
            'return_url.url' => 'return_url must be a valid URL',
        ]);

        $returnUrl = $validated['return_url'];

        // Ensure return_url uses https (security: prevent open redirect to non-HTTPS)
        $parsed = parse_url($returnUrl);
        if (($parsed['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([
                'return_url' => ['return_url must use HTTPS'],
            ]);
        }

        $user = $request->user();

        try {
            $user->getBtcPayApiKeyOrFail();
        } catch (\Throwable $e) {
            Log::warning('WooCommerce Satoshi Tickets connect: user has no BTCPay API key', [
                'user_id' => $user->id,
                'return_url' => $returnUrl,
            ]);

            return $this->redirectWithError($returnUrl, 'btcpay_not_configured');
        }

        $stores = $user->stores()->orderBy('name')->get();
        if ($stores->isEmpty()) {
            Log::warning('WooCommerce Satoshi Tickets connect: user has no stores', [
                'user_id' => $user->id,
                'return_url' => $returnUrl,
            ]);

            return $this->redirectWithError($returnUrl, 'no_stores');
        }

        // Single store: redirect immediately
        if ($stores->count() === 1) {
            return $this->redirectToReturnUrl($returnUrl, $stores->first(), $user);
        }

        // Multiple stores: check if store_id provided
        $storeId = $request->query('store_id');
        if ($storeId) {
            $store = $stores->firstWhere('id', $storeId);
            if ($store) {
                return $this->redirectToReturnUrl($returnUrl, $store, $user);
            }
        }

        // Multiple stores, no selection: show store picker
        return view('woocommerce.satoshi-tickets.connect', [
            'stores' => $stores,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * Handle form submission from store picker.
     */
    public function selectStore(Request $request)
    {
        $validated = $request->validate([
            'return_url' => ['required', 'url', 'max:2000'],
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
        ]);

        $returnUrl = $validated['return_url'];
        $parsed = parse_url($returnUrl);
        if (($parsed['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([
                'return_url' => ['return_url must use HTTPS'],
            ]);
        }

        $store = Store::findOrFail($validated['store_id']);
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        return $this->redirectToReturnUrl($returnUrl, $store, $request->user());
    }

    private function redirectToReturnUrl(string $returnUrl, Store $store, $user): \Illuminate\Http\RedirectResponse
    {
        $btcpayUrl = rtrim(config('services.btcpay.base_url', 'https://satflux.org'), '/');
        $apiKey = $user->getBtcPayApiKeyOrFail();

        $params = http_build_query([
            'satflux_return' => '1',
            'btcpay_url' => $btcpayUrl,
            'api_key' => $apiKey,
            'store_id' => $store->btcpay_store_id,
        ]);

        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        $targetUrl = $returnUrl . $separator . $params;

        Log::info('WooCommerce Satoshi Tickets connect: redirecting to return_url', [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        return redirect()->away($targetUrl);
    }

    private function redirectWithError(string $returnUrl, string $errorCode): \Illuminate\Http\RedirectResponse
    {
        $params = http_build_query([
            'satflux_return' => '1',
            'error' => $errorCode,
        ]);

        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        $targetUrl = $returnUrl . $separator . $params;

        return redirect()->away($targetUrl);
    }
}
