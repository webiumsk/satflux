<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to Stripe settings when plan does not allow (Pro+ or admin/support only).
 */
class EnsurePlanAllowsStripe
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

        if ($this->subscriptionService->canAccessStripe($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('messages.stripe_available_in_pro', ['default' => 'Stripe is available in Pro. Please upgrade to configure Stripe payments for your store.']),
        ], 403);
    }
}
