<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;

/** Purchase availability is independent of feature access and settled billing. */
class PurchaseAvailabilityPolicy
{
    /** @return array{pro_subscriptions: bool, pro_credits: bool, company_slots: bool, isdoc_packs: bool} */
    public function snapshot(): array
    {
        return [
            'pro_subscriptions' => (bool) config('access.pro_purchases_enabled', false),
            'pro_credits' => (bool) config('access.pro_purchases_enabled', false),
            'company_slots' => (bool) config('access.company_slot_purchases_enabled', false),
            'isdoc_packs' => (bool) config('access.isdoc_pack_purchases_enabled', false),
        ];
    }

    /** @return array<string, mixed> */
    public function publicDescriptor(): array
    {
        return [
            'promotion_active' => (bool) config('access.free_pro_enabled', false),
            'promotion_ends_at' => null,
            'policy_version' => (string) config('access.policy_version', 'free-pro-v1'),
            'purchase_availability' => $this->snapshot(),
        ];
    }

    public function assertAllowed(string $category): void
    {
        $code = match ($category) {
            'pro_subscriptions', 'pro_credits' => 'pro_purchase_temporarily_disabled',
            'company_slots' => 'company_slot_purchase_temporarily_disabled',
            'isdoc_packs' => 'isdoc_pack_purchase_temporarily_disabled',
            default => throw new \InvalidArgumentException('Unknown purchase category.'),
        };

        if (! $this->snapshot()[$category]) {
            throw new HttpResponseException(response()->json([
                'code' => $code,
                'message' => __("messages.{$code}"),
            ], 403));
        }
    }

    /**
     * Resolve actual provider billing identity, never the user's role or effective plan.
     * When PRO sales are closed, only the configured Enterprise plan may bypass the gate.
     */
    public function assertProviderPlanAllowed(?string $planId, string $category): void
    {
        if ($this->isEnterpriseProviderPlan($planId)) {
            return;
        }

        $this->assertAllowed($category);
    }

    /** Subscriber-specific UI hint; authoritative purchase checks run again on POST. */
    public function creditPurchasesAvailable(?string $planId): bool
    {
        return $this->isEnterpriseProviderPlan($planId) || $this->snapshot()['pro_credits'];
    }

    private function isEnterpriseProviderPlan(?string $planId): bool
    {
        $enterpriseId = config('services.btcpay.subscription_plans.enterprise');
        $proId = config('services.btcpay.subscription_plans.pro');

        return is_string($enterpriseId) && $enterpriseId !== ''
            && $planId === $enterpriseId && $planId !== $proId;
    }
}
