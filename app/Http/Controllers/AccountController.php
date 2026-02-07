<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        // Ensure role is visible in response
        $user->makeVisible('role');
        return response()->json($user);
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
            $storeCount = $user->stores()->count();
            $apiKeyCount = 0;
            foreach ($user->stores as $store) {
                $apiKeyCount += $store->apiKeys()->count();
            }
            return response()->json([
                'stores' => [
                    'current' => $storeCount,
                    'max' => null,
                    'unlimited' => true,
                ],
                'ln_addresses' => [
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

            $storeCount = $user->stores()->count();
            $apiKeyCount = 0;
            foreach ($user->stores as $store) {
                $apiKeyCount += $store->apiKeys()->count();
            }

            return [
                'stores' => [
                    'current' => $storeCount,
                    'max' => $maxStores,
                    'unlimited' => false,
                ],
                'ln_addresses' => [
                    'max' => $maxLnAddresses,
                    'unlimited' => $maxLnAddresses === null,
                ],
                'api_keys' => [
                    'current' => $apiKeyCount,
                    'max' => $maxApiKeys,
                    'unlimited' => false,
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
}








