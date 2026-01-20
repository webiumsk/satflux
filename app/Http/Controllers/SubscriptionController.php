<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create a plan checkout and return the checkout URL.
     * 
     * POST /api/subscriptions/checkout
     * Body: { storeUuid, planId, offeringId, customerEmail? }
     */
    public function checkout(Request $request)
    {
        // Feature flag: allow non-authenticated users to checkout
        // For MVP, we require auth, but this can be made optional later
        $allowGuestCheckout = config('services.btcpay.allow_guest_subscriptions', false);
        
        if (!$allowGuestCheckout && !$request->user()) {
            return response()->json([
                'message' => 'Authentication required to create checkout',
            ], 401);
        }

        $request->validate([
            'plan' => ['required_without_all:planId,storeUuid', 'string', 'in:pro,enterprise'],
            'storeUuid' => ['required_without:plan', 'string', 'exists:stores,id'],
            'planId' => ['required_without:plan', 'string'],
            'offeringId' => ['required_without:plan', 'string'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
        ]);

        // If plan name is provided, use subscription store config
        if ($request->has('plan')) {
            $storeUuid = config('services.btcpay.subscription_store_uuid');
            $offeringId = config('services.btcpay.subscription_offering_id');
            $planId = config("services.btcpay.subscription_plans.{$request->input('plan')}");

            if (!$storeUuid || !$offeringId || !$planId) {
                return response()->json([
                    'message' => 'Subscription configuration is incomplete. Please contact support.',
                ], 500);
            }

            $store = Store::findOrFail($storeUuid);
        } else {
            // Use provided IDs (for custom integrations)
            $store = Store::findOrFail($request->input('storeUuid'));
            $offeringId = $request->input('offeringId');
            $planId = $request->input('planId');
        }

        // For authenticated users, verify they own the store (unless feature flag allows guest)
        if (!$allowGuestCheckout && $request->user()) {
            // Optional: verify ownership if user is authenticated
            // For subscription checkout, we might want to allow any user to subscribe
            // So we'll skip ownership check for now, but validate store exists
        }

        try {
            $options = [];

            // Add customer email if provided
            if ($request->filled('customerEmail')) {
                $options['newSubscriberEmail'] = $request->input('customerEmail');
            } elseif ($request->user() && $request->user()->email) {
                // Use authenticated user's email if available
                $options['newSubscriberEmail'] = $request->user()->email;
            }

            // Build success redirect URL with checkout ID
            // We'll include the checkout ID in the URL so we can track it
            $baseUrl = config('app.url');
            $successUrl = config('services.btcpay.subscription_success_url', "{$baseUrl}/billing/success");
            $options['successRedirectUrl'] = $successUrl;

            // Add cancel URL if configured
            if (config('services.btcpay.subscription_cancel_url')) {
                $options['cancelRedirectUrl'] = config('services.btcpay.subscription_cancel_url');
            }

            // Create checkout via BTCPay
            $checkout = $this->subscriptionService->createPlanCheckout(
                $store->btcpay_store_id, // NEVER expose this to frontend, only use internally
                $offeringId,
                $planId,
                $options
            );

            // Update success URL with checkout ID if needed
            if (strpos($options['successRedirectUrl'], '{checkout}') !== false) {
                $checkout['checkoutUrl'] = str_replace('{checkout}', $checkout['checkoutId'], $checkout['checkoutUrl']);
            }

            Log::info('Checkout created via API', [
                'checkout_id' => $checkout['checkoutId'],
                'store_uuid' => $store->id, // Only log UUID, never btcpay_store_id
                'user_id' => $request->user()?->id,
            ]);

            // Return only safe data - never expose btcpay_store_id
            return response()->json([
                'checkoutUrl' => $checkout['checkoutUrl'],
                'checkoutId' => $checkout['checkoutId'],
                'expiresAt' => $checkout['expiresAt'] ?? null,
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            $statusCode = $e->getStatusCode() ?: 500;
            $errorMessage = $e->getMessage();

            Log::error('Failed to create subscription checkout', [
                'store_uuid' => $store->id,
                'plan' => $request->input('plan'),
                'plan_id' => $planId ?? $request->input('planId'),
                'offering_id' => $offeringId ?? $request->input('offeringId'),
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            // Map BTCPay errors to appropriate HTTP status codes
            if ($statusCode === 404) {
                return response()->json([
                    'message' => 'Plan or offering not found',
                ], 422);
            }

            if ($statusCode === 422) {
                return response()->json([
                    'message' => $errorMessage,
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to create checkout. Please try again later.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error creating subscription checkout', [
                'store_uuid' => $store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}

