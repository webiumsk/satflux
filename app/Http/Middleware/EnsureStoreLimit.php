<?php

namespace App\Http\Middleware;

use App\Services\EffectiveAccessPolicy;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Admin, Support and Enterprise users have unlimited access
        if ($user->hasUnlimitedAccess()) {
            return $next($request);
        }

        // Guest accounts: always at most one store (upgrade for more).
        if ((bool) ($user->is_guest ?? false)) {
            try {
                return Cache::lock('guest-store-provision:'.$user->id, 1800)->block(5, function () use ($user, $request, $next) {
                    $currentStoreCount = $user->stores()->count();
                    if ($currentStoreCount >= 1) {
                        return response()->json([
                            'message' => __('auth.guest_one_store_only'),
                            'code' => 'guest_store_limit',
                            'current_count' => $currentStoreCount,
                            'max_allowed' => 1,
                        ], 403);
                    }

                    // Hold the shared lock until remote and local provisioning
                    // completes so parallel requests cannot both pass the count.
                    return $next($request);
                });
            } catch (LockTimeoutException) {
                return response()->json([
                    'message' => __('auth.guest_store_setup_busy'),
                    'code' => 'guest_store_setup_busy',
                ], 503);
            }
        }

        // Get user's current subscription plan
        $policy = app(EffectiveAccessPolicy::class);
        $plan = $policy->plan($user);

        // Get current store count
        $currentStoreCount = $user->stores()->count();

        // Check limit
        $maxStores = $policy->limit($user, 'max_stores');

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
                'plan' => ($plan ? (string) $plan->getAttribute('display_name') : 'Free'),
            ], 403);
        }

        return $next($request);
    }
}
