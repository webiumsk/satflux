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

        $subscription = $user->currentSubscription();
        $plan = $user->currentSubscriptionPlan();

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
     * Update the user's profile information.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
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

    /**
     * Get current usage and plan limits for sidebar/UI (stores, LN addresses, API keys).
     * Cached per user for 60 seconds to avoid hammering BTCPay for LN count.
     */
    public function limits(Request $request)
    {
        $user = $request->user();
        $plan = $user->currentSubscriptionPlan();

        $cacheKey = 'account_limits_' . $user->id;
        $limits = Cache::remember($cacheKey, 60, function () use ($user, $plan) {
            $planCode = $plan ? strtolower($plan->code ?? $plan->name ?? '') : '';
            $isFreePlan = ($plan === null || $planCode === 'free');

            $storesCount = $user->stores()->count();
            $maxStores = $plan?->max_stores;
            if ($isFreePlan && $maxStores === null) {
                $maxStores = 1;
            }

            $apiKeysCount = 0;
            foreach ($user->stores as $store) {
                $apiKeysCount += $store->apiKeys()->count();
            }
            $maxApiKeys = $plan?->max_api_keys;
            if ($isFreePlan && $maxApiKeys === null) {
                $maxApiKeys = 1;
            }

            $lnAddressesCount = 0;
            $maxLnAddresses = $user->getMaxLightningAddresses();
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
                    'current' => $storesCount,
                    'max' => $maxStores,
                    'unlimited' => $maxStores === null,
                ],
                'ln_addresses' => [
                    'current' => $lnAddressesCount,
                    'max' => $maxLnAddresses,
                    'unlimited' => $maxLnAddresses === null,
                ],
                'api_keys' => [
                    'current' => $apiKeysCount,
                    'max' => $maxApiKeys,
                    'unlimited' => $maxApiKeys === null,
                ],
            ];
        });

        return response()->json($limits);
    }
}








