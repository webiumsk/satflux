<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;

/**
 * Public pricing: only FREE and PRO. Enterprise is internal (contact sales).
 */
class PlanController extends Controller
{
    /**
     * List plans for pricing page. Only free and pro (yearly).
     */
    public function index()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->whereIn('code', ['free', 'pro'])
            ->orderByRaw("CASE code WHEN 'free' THEN 0 WHEN 'pro' THEN 1 ELSE 2 END")
            ->get()
            ->map(function (SubscriptionPlan $plan) {
                return [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->display_name,
                    'price_eur' => (float) $plan->price_eur,
                    'billing_period' => $plan->billing_period ?? 'year',
                    'max_stores' => $plan->max_stores,
                    'max_api_keys' => $plan->max_api_keys,
                    'max_ln_addresses' => $plan->max_ln_addresses,
                    'features' => $plan->features ?? [],
                ];
            });

        return response()->json(['data' => $plans]);
    }
}
