<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks guest accounts from features that require a full (non-guest) account.
 */
class RejectGuestRestrictedFeatures
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && (bool) ($user->is_guest ?? false)) {
            return response()->json([
                'message' => __('auth.guest_feature_requires_account'),
                'code' => 'guest_feature_locked',
            ], 403);
        }

        return $next($request);
    }
}
