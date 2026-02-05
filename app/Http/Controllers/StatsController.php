<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/**
 * Stats: basic (Free) per store; advanced (Pro) per store, per PoS, overall.
 */
class StatsController extends Controller
{
    public function __construct(
        protected StatsService $statsService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Basic stats for one store (all plans).
     */
    public function store(Request $request, \App\Models\Store $store)
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $this->statsService->getBasicStoreStats($store);
        return response()->json(['data' => $data]);
    }

    /**
     * Advanced stats (per store, per PoS, overall). Pro only.
     */
    public function advanced(Request $request)
    {
        $user = $request->user();
        if (!$this->subscriptionService->canViewAdvancedStats($user)) {
            return response()->json([
                'message' => 'Advanced statistics are available on Pro. Please upgrade.',
            ], 403);
        }

        $data = $this->statsService->getAdvancedStats($user);
        return response()->json(['data' => $data]);
    }
}
