<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BtcPay\SubscriptionService as BtcPaySubscriptionService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    protected BtcPaySubscriptionService $btcpaySubscriptionService;
    protected SubscriptionService $subscriptionService;

    public function __construct(BtcPaySubscriptionService $btcpaySubscriptionService, SubscriptionService $subscriptionService)
    {
        $this->btcpaySubscriptionService = $btcpaySubscriptionService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create a plan checkout and return the checkout URL.
     * 
     * POST /api/subscriptions/checkout
     * Body: { plan: 'pro'|'enterprise', customerEmail? }
     * 
     * For custom integrations, can also use:
     * Body: { storeId, planId, offeringId, customerEmail? }
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
            'plan' => ['required_without_all:planId,storeId', 'string', 'in:pro,enterprise'],
            'storeId' => ['required_without:plan', 'string'],
            'planId' => ['required_without:plan', 'string'],
            'offeringId' => ['required_without:plan', 'string'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
        ]);

        // If plan name is provided, use subscription store config
        if ($request->has('plan')) {
            $storeId = config('services.btcpay.subscription_store_id');
            $offeringId = config('services.btcpay.subscription_offering_id');
            $planId = config("services.btcpay.subscription_plans.{$request->input('plan')}");

            if (!$storeId || !$offeringId || !$planId) {
                return response()->json([
                    'message' => 'Subscription configuration is incomplete. Please contact support.',
                ], 500);
            }
        } else {
            // Use provided IDs (for custom integrations)
            $storeId = $request->input('storeId');
            $offeringId = $request->input('offeringId');
            $planId = $request->input('planId');
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
            // Use BTCPay Store ID directly from config (no local Store record needed)
            $checkout = $this->btcpaySubscriptionService->createPlanCheckout(
                $storeId, // BTCPay Store ID from config
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
                'store_id' => $storeId, // BTCPay Store ID
                'plan' => $request->input('plan'),
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
                'store_id' => $storeId ?? 'unknown',
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
                'store_id' => $storeId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }

    /**
     * Handle subscription success redirect from BTCPay.
     * 
     * GET /api/subscriptions/success?checkoutPlanId=...
     * 
     * This endpoint processes the redirect after successful subscription checkout
     * and updates the user's role based on the plan they subscribed to.
     */
    public function success(Request $request)
    {
        $checkoutPlanId = $request->query('checkoutPlanId');

        if (!$checkoutPlanId) {
            return response()->json([
                'message' => 'Missing checkoutPlanId parameter',
            ], 400);
        }

        try {
            // Get checkout details from BTCPay
            $checkoutDetails = $this->btcpaySubscriptionService->getPlanCheckout($checkoutPlanId);

            // Extract plan ID from nested structure
            // BTCPay returns plan ID in checkoutDetails['plan']['id'] or checkoutDetails['subscriber']['plan']['id']
            $planId = $checkoutDetails['plan']['id']
                ?? $checkoutDetails['subscriber']['plan']['id']
                ?? $checkoutDetails['planId']
                ?? null;

            // Extract subscription/customer ID
            $subscriptionId = $checkoutDetails['subscriber']['customer']['id']
                ?? $checkoutDetails['subscriptionId']
                ?? null;

            // Extract customer email from nested structure
            // BTCPay returns email in checkoutDetails['subscriber']['customer']['identities']['Email']
            $customerEmail = $checkoutDetails['subscriber']['customer']['identities']['Email']
                ?? $checkoutDetails['subscriber']['customer']['email']
                ?? $checkoutDetails['customerEmail']
                ?? $checkoutDetails['subscriberEmail']
                ?? $checkoutDetails['email']
                ?? null;

            if (!$planId) {
                Log::warning('Subscription success - plan ID not found in checkout details', [
                    'checkout_id' => $checkoutPlanId,
                    'checkout_details' => $checkoutDetails,
                    'checkout_keys' => array_keys($checkoutDetails),
                ]);
                return response()->json([
                    'message' => 'Plan information not found in checkout',
                ], 400);
            }

            // Map plan ID to plan name
            $subscriptionPlans = config('services.btcpay.subscription_plans', []);
            $planName = null;

            if ($planId === ($subscriptionPlans['pro'] ?? null)) {
                $planName = 'pro';
            } elseif ($planId === ($subscriptionPlans['enterprise'] ?? null)) {
                $planName = 'enterprise';
            }

            if (!$planName) {
                Log::warning('Subscription success - unknown plan ID', [
                    'checkout_id' => $checkoutPlanId,
                    'plan_id' => $planId,
                    'subscription_plans' => $subscriptionPlans,
                ]);
                return response()->json([
                    'message' => 'Unknown subscription plan',
                ], 400);
            }

            // Find user by email or session
            $user = null;
            if ($customerEmail) {
                $user = User::where('email', $customerEmail)->first();
            }

            // Fallback: if user not found by email, try to get from session
            if (!$user && $request->user()) {
                $user = $request->user();
            }

            if (!$user) {
                Log::warning('Subscription success - user not found', [
                    'checkout_id' => $checkoutPlanId,
                    'customer_email' => $customerEmail,
                    'has_session' => $request->user() !== null,
                ]);
                // Don't return error - just log, user might need to login
                return response()->json([
                    'message' => 'User not found. Please login to activate your subscription.',
                ], 200);
            }

            // Activate or extend subscription using SubscriptionService
            // This creates/updates subscription record and handles 1-year extension
            $subscription = $this->subscriptionService->activateSubscription(
                $user,
                $planName,
                $subscriptionId
            );

            // Update user role for backward compatibility (legacy field)
            $oldRole = $user->role;
            $user->role = $planName;
            if ($subscriptionId) {
                $user->btcpay_subscription_id = $subscriptionId;
            }
            $user->save();

            Log::info('Subscription activated after checkout success', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $planName,
                'checkout_id' => $checkoutPlanId,
                'plan_id' => $planId,
                'subscription_id' => $subscription->id,
                'expires_at' => $subscription->expires_at,
            ]);

            return response()->json([
                'message' => 'Subscription activated successfully',
                'plan' => $planName,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'expires_at' => $subscription->expires_at,
                ],
                'user' => $user->makeVisible('role'),
            ]);

        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('Failed to process subscription success', [
                'checkout_id' => $checkoutPlanId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => 'Failed to process subscription. Please contact support.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error processing subscription success', [
                'checkout_id' => $checkoutPlanId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred. Please contact support.',
            ], 500);
        }
    }

    /**
     * Get user's subscription details.
     * 
     * GET /api/subscriptions/details
     */
    public function details(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $storeId = config('services.btcpay.subscription_store_id');
        $offeringId = config('services.btcpay.subscription_offering_id');

        try {
            // Get subscriber details using email as selector
            $subscriber = $this->btcpaySubscriptionService->getSubscriber($storeId, $offeringId, $user->email);

            // Get credit balance
            $creditBalance = 0;
            try {
                $credits = $this->btcpaySubscriptionService->getSubscriberCredits($storeId, $offeringId, $user->email, 'SATS');
                $creditBalance = $credits['balance'] ?? $credits['amount'] ?? 0;
            } catch (\Exception $e) {
                // Credit endpoint might not be available or user might not have credits yet
                Log::debug('Could not fetch credit balance', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'subscriber' => $subscriber,
                'creditBalance' => $creditBalance,
            ]);

        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            if ($e->getStatusCode() === 404) {
                // User doesn't have a subscription yet
                return response()->json([
                    'subscriber' => null,
                    'creditBalance' => 0,
                ]);
            }

            Log::error('Failed to get subscription details', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch subscription details',
            ], 500);
        }
    }

    /**
     * Get subscriber credit balance.
     * 
     * GET /api/subscriptions/credits
     */
    public function getCredits(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $storeId = config('services.btcpay.subscription_store_id');
        $offeringId = config('services.btcpay.subscription_offering_id');
        $currency = $request->query('currency', 'SATS');

        try {
            $credits = $this->btcpaySubscriptionService->getSubscriberCredits($storeId, $offeringId, $user->email, $currency);

            return response()->json([
                'balance' => $credits['balance'] ?? $credits['amount'] ?? 0,
                'currency' => $currency,
                'details' => $credits,
            ]);

        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('Failed to get credit balance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch credit balance',
            ], 500);
        }
    }

    /**
     * Add credit to subscriber account.
     * 
     * POST /api/subscriptions/credits
     * Body: { amount: number, currency?: string }
     */
    public function addCredits(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'in:SATS,BTC'],
        ]);

        $storeId = config('services.btcpay.subscription_store_id');
        $offeringId = config('services.btcpay.subscription_offering_id');
        $currency = $request->input('currency', 'SATS');
        $amount = $request->input('amount');

        try {
            // Add credit via BTCPay API (this will create an invoice for the credit)
            $description = $request->input('description', 'Credit purchase');
            $result = $this->btcpaySubscriptionService->addSubscriberCredits($storeId, $offeringId, $user->email, $currency, $amount, $description);

            // BTCPay returns invoice information in the response
            $invoiceId = $result['invoiceId'] ?? null;
            $invoiceUrl = $result['invoiceUrl'] ?? $result['url'] ?? null;

            // If invoice URL is not in response, construct it from base URL and invoice ID
            if (!$invoiceUrl && $invoiceId) {
                $baseUrl = config('services.btcpay.base_url');
                $invoiceUrl = "{$baseUrl}/i/{$invoiceId}";
            }

            return response()->json([
                'message' => 'Credit invoice created successfully',
                'invoiceId' => $invoiceId,
                'invoiceUrl' => $invoiceUrl,
                'details' => $result,
            ]);

        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('Failed to add credit', [
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => $e->getMessage() ?: 'Failed to add credit',
            ], $e->getStatusCode() ?: 500);
        }
    }
}

