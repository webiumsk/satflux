<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks creating new panel API key when user reaches max_api_keys for their plan.
 * Counts user_api_keys (panel API), not store API keys.
 */
class EnsurePlanAllowsUserApiKeyCreation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasUnlimitedAccess()) {
            return $next($request);
        }

        $plan = $user->currentSubscriptionPlan();
        $max = $plan?->max_api_keys;
        if ($max === null) {
            return $next($request);
        }

        $currentCount = $user->userApiKeys()->whereNull('revoked_at')->count();
        if ($currentCount >= $max) {
            return response()->json([
                'message' => "You have reached the maximum number of API keys ({$max}) for your plan.",
                'current_count' => $currentCount,
                'max_allowed' => $max,
                'plan' => $plan?->display_name ?? 'Free',
            ], 403);
        }

        return $next($request);
    }
}
