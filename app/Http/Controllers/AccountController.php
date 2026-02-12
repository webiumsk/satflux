<?php

namespace App\Http\Controllers;

use App\Services\BtcPay\LightningAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(
        protected LightningAddressService $lightningAddressService
    ) {}
    /**
     * Get the authenticated user with plan and subscription info.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $user->makeVisible('role');

        $user->load([
            'subscriptions' => fn ($q) => $q->whereIn('status', ['active', 'grace'])
                ->orderBy('expires_at', 'desc')
                ->with('plan'),
        ]);
        $subscription = $user->subscriptions->first();
        $plan = $subscription?->plan ?? \App\Models\SubscriptionPlan::where('code', 'free')->orWhere('name', 'free')->first();

        $payload = $user->toArray();
        $payload['plan'] = $plan ? [
            'code' => $plan->code,
            'name' => $plan->display_name,
            'max_stores' => $plan->max_stores,
            'max_api_keys' => $plan->max_api_keys,
            'max_ln_addresses' => $user->getMaxLightningAddresses(),
            'features' => $plan->features ?? [],
        ] : null;
        $payload['subscription'] = $subscription ? [
            'status' => $subscription->status,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
        ] : null;
        $payload['plan_features'] = [
            'advanced_stats' => $user->planFeature('advanced_statistics'),
            'automatic_exports' => $user->planFeature('automatic_csv_exports'),
            'offline_payment_methods' => $user->planFeature('offline_payment_methods'),
        ];

        return response()->json($payload);
    }

    /**
     * Get account limits (stores, LN addresses, API keys) for the authenticated user.
     * Admin and support always get unlimited for all.
     */
    public function limits(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Admin and support have unlimited access to all features
        if ($user->hasUnlimitedAccess()) {
            $user->load(['stores' => fn ($q) => $q->withCount('apiKeys')]);
            $storeCount = $user->stores->count();
            $apiKeyCount = (int) $user->stores->sum('api_keys_count');
            return response()->json([
                'stores' => [
                    'current' => $storeCount,
                    'max' => null,
                    'unlimited' => true,
                ],
                'ln_addresses' => [
                    'current' => 0,
                    'max' => null,
                    'unlimited' => true,
                ],
                'api_keys' => [
                    'current' => $apiKeyCount,
                    'max' => null,
                    'unlimited' => true,
                ],
            ]);
        }

        $cacheKey = 'user_limits_' . $user->id;
        $limits = Cache::remember($cacheKey, 60, function () use ($user) {
            $plan = $user->currentSubscriptionPlan();
            $maxStores = $plan?->max_stores;
            $maxApiKeys = $plan?->max_api_keys;
            // Free plan fallback when plan is null or has null limits
            if ($maxStores === null) {
                $maxStores = 1;
            }
            if ($maxApiKeys === null) {
                $maxApiKeys = 1;
            }
            $maxLnAddresses = $user->getMaxLightningAddresses();

            $user->load([
                'stores' => fn ($q) => $q->withCount(['apiKeys as active_api_keys_count' => fn ($q) => $q->where('is_active', true)]),
            ]);
            $storeCount = $user->stores->count();
            $apiKeyCount = (int) ($user->stores->max('active_api_keys_count') ?? 0);
            $lnAddressesCount = 0;
            if ($maxLnAddresses !== null || $user->hasUnlimitedAccess()) {
                try {
                    $apiKey = $user->getBtcPayApiKeyOrFail();
                    foreach ($user->stores as $store) {
                        $list = $this->lightningAddressService->listAddresses(
                            $store->btcpay_store_id,
                            $apiKey
                        );
                        $lnAddressesCount += is_array($list) ? count($list) : 0;
                    }
                } catch (\Throwable $e) {
                    // Leave count at 0 on BTCPay errors
                }
            }

            return [
                'stores' => [
                    'current' => $storeCount,
                    'max' => $maxStores,
                    'unlimited' => false,
                ],
                'ln_addresses' => [
                    'current' => $lnAddressesCount,
                    'max' => $maxLnAddresses,
                    'unlimited' => $maxLnAddresses === null,
                ],
                'api_keys' => [
                    'current' => $apiKeyCount,
                    'max' => $maxApiKeys,
                    'unlimited' => $maxApiKeys === null,
                ],
            ];
        });

        return response()->json($limits);
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => __('messages.profile_updated'),
            'user' => $request->user()->fresh(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => __('messages.password_updated')]);
    }
}








