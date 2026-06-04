<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks creating new invoicing companies when the plan company limit is reached.
 * Existing companies are never removed when a subscription lapses.
 */
class EnsureCompanyLimit
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if ($this->subscriptionService->canCreateCompany($user)) {
            return $next($request);
        }

        $max = $this->subscriptionService->maxCompaniesForUser($user);
        $current = $user->companies()->count();

        return response()->json([
            'message' => __('messages.company_limit_reached', [
                'max' => $max ?? 0,
                'default' => 'You have reached the maximum number of companies for your plan.',
            ]),
            'code' => 'company_limit',
            'current_count' => $current,
            'max_allowed' => $max,
        ], 403);
    }
}
