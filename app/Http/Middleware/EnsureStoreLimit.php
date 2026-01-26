<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user doesn't exceed store limit.
 * 
 * IMPORTANT: This is a non-custodial system. This middleware:
 * - Blocks creation of NEW stores if limit is reached
 * - NEVER affects existing stores or payment acceptance
 * - Enforces limits based on subscription plan
 */
class EnsureStoreLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Get user's current subscription plan
        $plan = $user->currentSubscriptionPlan();

        // Get current store count
        $currentStoreCount = $user->stores()->count();

        // Check limit
        $maxStores = $plan?->max_stores;

        // If unlimited (null), allow
        if ($maxStores === null) {
            return $next($request);
        }

        // If limit reached, block
        if ($currentStoreCount >= $maxStores) {
            return response()->json([
                'message' => "You have reached the maximum number of stores ({$maxStores}) for your plan.",
                'current_count' => $currentStoreCount,
                'max_allowed' => $maxStores,
                'plan' => $plan?->display_name ?? 'Free',
            ], 403);
        }

        return $next($request);
    }
}

