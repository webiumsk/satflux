<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user doesn't exceed API key limit.
 * 
 * IMPORTANT: This is a non-custodial system. This middleware:
 * - Blocks creation of NEW API keys if limit is reached
 * - NEVER affects existing API keys or payment acceptance
 * - Enforces limits based on subscription plan
 */
class EnsureApiKeyLimit
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

        // Get current API key count across all stores
        $currentApiKeyCount = 0;
        foreach ($user->stores as $store) {
            $currentApiKeyCount += $store->apiKeys()->count();
        }

        // Check limit
        $maxApiKeys = $plan?->max_api_keys;

        // If unlimited (null), allow
        if ($maxApiKeys === null) {
            return $next($request);
        }

        // If limit reached, block
        if ($currentApiKeyCount >= $maxApiKeys) {
            return response()->json([
                'message' => "You have reached the maximum number of API keys ({$maxApiKeys}) for your plan.",
                'current_count' => $currentApiKeyCount,
                'max_allowed' => $maxApiKeys,
                'plan' => $plan?->display_name ?? 'Free',
            ], 403);
        }

        return $next($request);
    }
}

