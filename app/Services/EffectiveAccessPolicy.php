<?php

namespace App\Services;

use App\Models\CompanySlotPurchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Read-only feature access. Billing and authorization remain separate authorities.
 * No subscription, role, verification or trial state is changed by this policy.
 */
class EffectiveAccessPolicy
{
    private const PRO_FEATURES = [
        'manual_csv_exports', 'automatic_csv_exports', 'advanced_statistics',
        'basic_payment_overview', 'offline_payment_methods', 'priority_support',
        'stripe', 'business_invoicing', 'xlsx_exports', 'custom_branding',
        'store_customization', 'advanced_pos', 'app_archiving',
    ];

    private const ENTERPRISE_FEATURES = [
        'per_store_user_management', 'expense_isdoc_extract_unlimited',
    ];

    /** @return array{plan: ?SubscriptionPlan, code: string, source: string} */
    private function resolve(User $user): array
    {
        if ($user->hasUnlimitedAccess()) {
            return [
                'plan' => SubscriptionPlan::where('code', 'enterprise')->first(),
                'code' => 'enterprise',
                'source' => 'privileged',
            ];
        }

        $subscription = $user->currentSubscription();
        $subscriptionPlan = $subscription?->getRelationValue('plan');
        $plan = $subscription && $this->subscriptionIsCurrent($subscription) && $subscriptionPlan instanceof SubscriptionPlan
            ? $subscriptionPlan
            : ($subscription ? SubscriptionPlan::where('code', 'free')->first() : $user->currentSubscriptionPlan());
        $code = strtolower($plan ? (string) $plan->getAttribute('code') : 'free');
        if (! in_array($code, ['free', 'pro', 'enterprise'], true)) {
            $plan = SubscriptionPlan::where('code', 'free')->first();
            $code = 'free';
        }
        $source = $code === 'free' ? 'free' : ($subscription ? 'subscription' : 'legacy');

        if ($code === 'free' && $this->isPromotionEligible($user)) {
            $pro = SubscriptionPlan::where('code', 'pro')->first();
            if ($pro) {
                return ['plan' => $pro, 'code' => 'pro', 'source' => 'promotion'];
            }
        }

        return ['plan' => $plan, 'code' => $code, 'source' => $source];
    }

    private function subscriptionIsCurrent(Subscription $subscription): bool
    {
        $expiresAt = $subscription->getAttribute('expires_at');
        if ($subscription->billing_phase === Subscription::BILLING_EXPIRED
            || ! in_array($subscription->status, ['active', 'grace'], true)
            || ! $expiresAt instanceof CarbonInterface) {
            return false;
        }
        if ($subscription->status === 'active' && now()->isBefore($expiresAt)) {
            return true;
        }
        if ($subscription->isTrial()) {
            return false;
        }
        $graceEnds = $subscription->getAttribute('grace_ends_at');
        if (! $graceEnds instanceof CarbonInterface) {
            $graceEnds = $expiresAt->copy()->addDays((int) config('pricing.grace_days', 30));
        }

        return $expiresAt->lessThanOrEqualTo(now()) && now()->isBefore($graceEnds);
    }

    public function isPromotionEligible(User $user): bool
    {
        return (bool) config('access.free_pro_enabled', false)
            && ! (bool) $user->is_guest && $user->hasVerifiedEmail();
    }

    public function plan(User $user): ?SubscriptionPlan
    {
        return $this->resolve($user)['plan'];
    }

    /** @param array{plan: ?SubscriptionPlan, code: string, source: string} $access
     * @return list<string>
     */
    private function features(array $access): array
    {
        $features = $access['plan']?->getAttribute('features');
        $features = is_array($features) ? $features : [];
        if ($access['code'] === 'enterprise') {
            return array_values(array_unique(array_merge($features, self::PRO_FEATURES, self::ENTERPRISE_FEATURES)));
        }
        if ($access['code'] === 'pro') {
            // A configured PRO plan can never confer Enterprise capabilities.
            return array_values(array_unique(array_merge(
                array_values(array_intersect($features, self::PRO_FEATURES)),
                ['xlsx_exports', 'custom_branding', 'store_customization', 'advanced_pos', 'app_archiving'],
            )));
        }

        return array_values(array_intersect($features, ['manual_csv_exports', 'basic_payment_overview']));
    }

    public function allows(User $user, string $feature): bool
    {
        return in_array($feature, $this->features($this->resolve($user)), true);
    }

    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        $access = $this->resolve($user);
        $plan = $access['plan'];
        $features = $this->features($access);
        $unlimited = $access['code'] === 'enterprise';
        $free = $access['code'] === 'free';
        // Null is unlimited only for an existing paid plan, never an absent plan.
        $limit = static fn (string $key, int $fallback): ?int => $unlimited
            ? null : ($plan ? ($free ? (int) ($plan->$key ?? $fallback) : $plan->$key) : $fallback);
        $included = in_array('business_invoicing', $features, true) ? $limit('max_companies', 0) : 0;
        $betaMax = config('invoicing.beta_pro_max_companies');
        if ($access['code'] === 'pro' && $betaMax !== null && $betaMax > 0) {
            $included = (int) $betaMax;
        }
        $extra = (int) CompanySlotPurchase::where('user_id', $user->id)
            ->where('status', CompanySlotPurchase::STATUS_PAID)->sum('slots');

        return [
            'plan_code' => $access['code'],
            'source' => $access['source'],
            'features' => $features,
            'limits' => [
                'max_stores' => (bool) $user->is_guest && ! $unlimited ? 1 : $limit('max_stores', 1),
                'max_api_keys' => $limit('max_api_keys', 1),
                'max_user_api_keys' => $limit('max_api_keys', 1),
                'max_ln_addresses' => $limit('max_ln_addresses', 1),
                'max_events' => $limit('max_events', 1),
                'max_raffles' => (bool) $user->is_guest ? 0 : ($free ? 1 : null),
                'max_companies' => $included === null || $included <= 0 ? $included : $included + $extra,
                'included_companies' => $included,
                'extra_company_slots' => $extra,
            ],
            'policy_version' => (string) config('access.policy_version', 'free-pro-v1'),
            'promotion' => [
                'active' => $access['source'] === 'promotion',
                'id' => $access['source'] === 'promotion' ? 'free-pro' : null,
                'ends_at' => null,
            ],
        ];
    }

    public function limit(User $user, string $key): ?int
    {
        $limits = $this->snapshot($user)['limits'];
        if (! array_key_exists($key, $limits)) {
            throw new \InvalidArgumentException('Unknown effective access limit.');
        }

        return $limits[$key];
    }
}
