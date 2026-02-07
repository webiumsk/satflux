<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks scheduling automatic monthly exports when plan does not allow automatic_csv_exports.
 */
class EnsurePlanAllowsAutoExports
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

        if ($this->subscriptionService->canUseAutomaticExports($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Automatic monthly exports are not available on your plan. Please upgrade to Pro.',
        ], 403);
    }
}
