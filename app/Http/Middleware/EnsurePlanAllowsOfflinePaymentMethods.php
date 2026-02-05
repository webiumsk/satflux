<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks enabling cash/card in PoS settings when plan is Free.
 */
class EnsurePlanAllowsOfflinePaymentMethods
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasUnlimitedAccess()) {
            return $next($request);
        }

        if ($this->subscriptionService->canUseOfflinePaymentMethods($user)) {
            return $next($request);
        }

        $enabled = $request->input('settings_json.enabled_payment_methods')
            ?? $request->input('enabled_payment_methods')
            ?? [];
        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true) ?? [];
        }
        $wantsOffline = !empty(array_intersect(['cash', 'card'], $enabled));

        if ($wantsOffline) {
            return response()->json([
                'message' => '"Mark as Paid in Cash" and "Mark as Paid by Card" are available on Pro. Please upgrade.',
            ], 403);
        }

        return $next($request);
    }
}
