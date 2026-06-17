<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanAllowsBusinessInvoicing
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if ($this->subscriptionService->canUseBusinessInvoicing($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('messages.business_invoicing_available_in_pro', [
                'default' => 'Business invoicing is available on the PRO plan. Please upgrade to create invoices.',
            ]),
        ], 403);
    }
}
