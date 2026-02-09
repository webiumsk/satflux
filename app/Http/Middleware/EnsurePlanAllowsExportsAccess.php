<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to exports list when plan does not allow (Pro+ or admin/support only).
 */
class EnsurePlanAllowsExportsAccess
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

        if ($this->subscriptionService->canAccessExports($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Export history is available in Pro. Please upgrade to access your export history and automatic monthly exports.',
        ], 403);
    }
}
