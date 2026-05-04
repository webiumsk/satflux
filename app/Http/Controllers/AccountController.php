<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\LightningAddressService;
use App\Services\BtcPay\TicketService;
use App\Services\GuestUpgradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(
        protected LightningAddressService $lightningAddressService,
        protected TicketService $ticketService,
        protected GuestUpgradeService $guestUpgradeService
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
        $payload['has_lightning_login'] = ! empty($user->lightning_public_key);
        $payload['has_nostr_login'] = ! empty($user->nostr_public_key);
        $payload['guest_recovery_enrolled'] = (bool) ($user->is_guest ?? false)
            && ! empty($user->guest_recovery_public_key ?? null);

        return response()->json($payload);
    }

    /**
     * Get account limits (stores, LN addresses, API keys) for the authenticated user.
     * Admin and support always get unlimited for all.
     */
    public function limits(Request $request)
    {
        $user = $request->user();

        if (! $user) {
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
                    'current' => 0,
                    'max' => null,
                    'unlimited' => true,
                ],
                'api_keys' => [
                    'current' => $apiKeyCount,
                    'max' => null,
                    'unlimited' => true,
                ],
                'events' => [
                    'max' => null,
                    'unlimited' => true,
                ],
            ]);
        }

        $cacheKey = 'user_limits_'.$user->id;
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
            // API key limit is per store; for account we show max active keys in any single store
            $apiKeyCount = 0;
            $lnAddressesCount = 0;
            foreach ($user->stores as $store) {
                $activeInStore = $store->apiKeys()->where('is_active', true)->count();
                if ($activeInStore > $apiKeyCount) {
                    $apiKeyCount = $activeInStore;
                }
            }
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

            $maxEvents = $user->getMaxEventsPerStore();

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
                'events' => [
                    'max' => $maxEvents,
                    'unlimited' => $maxEvents === null,
                ],
            ];
        });

        // When store_id is provided, add events.current (event count for that store) for sidebar display
        if ($request->filled('store_id')) {
            $store = $user->stores()->find($request->store_id);
            if ($store) {
                try {
                    $apiKey = $user->getBtcPayApiKeyOrFail();
                    $eventList = $this->ticketService->listEvents($store->btcpay_store_id, $apiKey, false, true);
                    $limits['events']['current'] = is_array($eventList) ? count($eventList) : 0;
                } catch (\Throwable $e) {
                    $limits['events']['current'] = 0;
                }
            }
        }

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

    /**
     * Upgrade a guest account to a regular account identity.
     * Supports:
     * - email+password (recommended)
     * - lightning / nostr linked login (guest flag removed, email unchanged)
     */
    public function upgradeGuest(Request $request)
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->is_guest ?? false)) {
            return response()->json(['message' => 'Only guest accounts can be upgraded.'], 422);
        }

        $validated = $request->validate([
            'method' => ['required', 'in:email,lightning,nostr'],
            'email' => ['required_if:method,email', 'nullable', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required_if:method,email', 'nullable', Password::defaults()],
            'password_confirmation' => ['required_if:method,email', 'nullable', 'same:password'],
        ]);

        try {
            $upgradedUser = $this->guestUpgradeService->upgrade($user, $validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Guest account upgraded successfully.',
            'user' => $upgradedUser->makeVisible('role'),
        ]);
    }
}
