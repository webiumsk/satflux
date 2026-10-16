<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailCodeChallengeException;
use App\Models\Store;
use App\Services\Auth\EmailCodeChallengeService;
use App\Services\BtcPay\LightningAddressService;
use App\Services\BtcPay\RaffleService;
use App\Services\BtcPay\TicketService;
use App\Services\Compliance\ComplianceGate;
use App\Services\EffectiveAccessPolicy;
use App\Services\GuestUpgradeService;
use App\Services\PurchaseAvailabilityPolicy;
use App\Support\Legal\LegalConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(
        protected LightningAddressService $lightningAddressService,
        protected TicketService $ticketService,
        protected RaffleService $raffleService,
        protected GuestUpgradeService $guestUpgradeService,
        protected ComplianceGate $complianceGate,
        protected EmailCodeChallengeService $emailCodes,
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
        $effective = app(EffectiveAccessPolicy::class)->snapshot($user);
        // The plan remains billing/legacy state; promotion is serialized separately.
        $includedCompanies = $plan?->getAttribute('max_companies');
        if ($plan?->code === 'pro' && config('invoicing.beta_pro_max_companies') > 0) {
            $includedCompanies = (int) config('invoicing.beta_pro_max_companies');
        }
        $maxCompanies = $includedCompanies === null ? null
            : ($includedCompanies > 0 ? $includedCompanies + $effective['limits']['extra_company_slots'] : 0);
        $payload['effective_access'] = $effective;
        $payload['purchase_availability'] = app(PurchaseAvailabilityPolicy::class)->snapshot();

        $payload['plan'] = $plan ? [
            'code' => $plan->code,
            'name' => $plan->display_name,
            'max_stores' => $plan->max_stores,
            'max_api_keys' => $plan->max_api_keys,
            'max_ln_addresses' => $plan->max_ln_addresses,
            'max_companies' => $maxCompanies,
            'included_companies' => $includedCompanies,
            'extra_company_slots' => ($maxCompanies !== null)
                ? max(0, $maxCompanies - $includedCompanies)
                : 0,
            'companies_unlimited' => $maxCompanies === null && ($user->hasUnlimitedAccess() || $plan->hasFeature('business_invoicing')),
            'features' => $plan->features ?? [],
        ] : null;
        $payload['subscription'] = $subscription ? [
            'status' => $subscription->status,
            'billing_phase' => $subscription->billing_phase,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
        ] : null;
        $payload['plan_features'] = [
            'advanced_stats' => in_array('advanced_statistics', $effective['features'], true),
            'automatic_exports' => in_array('automatic_csv_exports', $effective['features'], true),
            'offline_payment_methods' => in_array('offline_payment_methods', $effective['features'], true),
            'business_invoicing' => in_array('business_invoicing', $effective['features'], true),
            'expense_isdoc_extract_unlimited' => in_array('expense_isdoc_extract_unlimited', $effective['features'], true),
        ];
        $payload['guest_recovery_enrolled'] = ! empty($user->guest_recovery_public_key ?? null);
        // Public owner identifier for validating local recovery envelopes against this session.
        $payload['recovery_public_key'] = $user->guest_recovery_public_key;
        // The client renders the guest->account upgrade form from this flag so
        // it always matches the server-side validation (password nullable when
        // email-only) - no build-time VITE_* env pair to keep in sync.
        $payload['guest_upgrade_email_only'] = (bool) config('guest.upgrade_email_only');
        $payload['requires_recovery_migration'] = $user->requiresRecoveryMigration();
        $payload['can_use_password_login'] = $user->canUsePasswordLogin();
        // Staged guest -> Free upgrade awaiting its email code (lets the SPA
        // resume the code step after a reload). Never the hash or payload.
        $payload['pending_email_challenge'] = (bool) ($user->is_guest ?? false)
            ? $this->emailCodes->summary($this->guestUpgradeService->pending($user))
            : null;

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

        // Do not cache access decisions: verification, policy and billing can change
        // while old clients or workers still hold an earlier capability snapshot.
        $effective = app(EffectiveAccessPolicy::class)->snapshot($user);
        $limits = (function () use ($user, $effective) {
            $maxStores = $effective['limits']['max_stores'];
            $maxApiKeys = $effective['limits']['max_api_keys'];
            $maxLnAddresses = $effective['limits']['max_ln_addresses'];

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

            $maxEvents = $effective['limits']['max_events'];
            $maxRaffles = $effective['limits']['max_raffles'];
            $maxCompanies = $effective['limits']['max_companies'];
            $companyCount = $user->companies()->count();

            return [
                'stores' => [
                    'current' => $storeCount,
                    'max' => $maxStores,
                    'unlimited' => $maxStores === null,
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
                'raffles' => [
                    'max' => $maxRaffles,
                    'unlimited' => $maxRaffles === null,
                    'allowed' => $maxRaffles !== 0,
                ],
                'companies' => [
                    'current' => $companyCount,
                    'max' => $maxCompanies,
                    'unlimited' => $maxCompanies === null && in_array('business_invoicing', $effective['features'], true),
                ],
            ];
        })();
        $limits['policy_version'] = $effective['policy_version'];
        $limits['user_api_keys'] = [
            'current' => $user->userApiKeys()->whereNull('revoked_at')->count(),
            'max' => $effective['limits']['max_user_api_keys'],
            'unlimited' => $effective['limits']['max_user_api_keys'] === null,
        ];

        // When store_id is provided, add per-store counts for sidebar display
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
                try {
                    $apiKey = $user->getBtcPayApiKeyOrFail();
                    $raffleList = $this->raffleService->listRaffles($store->btcpay_store_id, $apiKey);
                    $limits['raffles']['current'] = is_array($raffleList) ? count($raffleList) : 0;
                } catch (\Throwable $e) {
                    $limits['raffles']['current'] = 0;
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
            'evolu_relay_url' => ['nullable', 'string', 'max:512', 'regex:/^wss:\/\/.+/i'],
        ]);

        if (array_key_exists('evolu_relay_url', $validated)) {
            $url = trim((string) ($validated['evolu_relay_url'] ?? ''));
            $validated['evolu_relay_url'] = $url !== '' ? $url : null;
        }

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
     * Guest -> Free, step 1: validate the address and email a 6-digit code.
     * The guest row is untouched until the code is confirmed. Password is
     * optional when upgrade_email_only.
     */
    public function requestGuestUpgrade(Request $request)
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->is_guest ?? false)) {
            return response()->json(['message' => 'Only guest accounts can be upgraded.'], 422);
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $emailOnlyUpgrade = (bool) config('guest.upgrade_email_only');

        $validated = $request->validate(array_merge([
            'method' => ['required', 'in:email'],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => $emailOnlyUpgrade
                ? ['nullable', 'confirmed', Password::defaults()]
                : ['required', 'confirmed', Password::defaults()],
        ], LegalConsent::registrationRules()));

        $this->complianceGate->assertRegistrationAllowed(
            $request,
            $validated['email'],
            is_string($user->name) ? $user->name : null,
        );

        try {
            $challenge = $this->guestUpgradeService->stage($user, $validated);
        } catch (EmailCodeChallengeException|ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => __('messages.guest_upgrade_code_sent'),
            'challenge' => $this->emailCodes->summary($challenge),
        ]);
    }

    /**
     * Guest -> Free, step 2: confirm the code and apply the staged upgrade.
     * Idempotent for an account that already finished (second tab).
     */
    public function confirmGuestUpgrade(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! (bool) ($user->is_guest ?? false)) {
            return response()->json([
                'message' => __('messages.guest_upgrade_already_done'),
                'user' => $user->makeVisible('role'),
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        try {
            $upgradedUser = $this->guestUpgradeService->confirm($user, $validated['code']);
        } catch (EmailCodeChallengeException|ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => __('messages.guest_upgrade_confirmed'),
            'user' => $upgradedUser->makeVisible('role'),
        ]);
    }

    public function resendGuestUpgrade(Request $request)
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->is_guest ?? false)) {
            return response()->json(['message' => 'Only guest accounts can be upgraded.'], 422);
        }

        try {
            $challenge = $this->guestUpgradeService->resend($user);
        } catch (EmailCodeChallengeException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => __('messages.guest_upgrade_code_sent'),
            'challenge' => $this->emailCodes->summary($challenge),
        ]);
    }
}
