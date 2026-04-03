<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    /**
     * Block authenticated users who have not verified their email (SPA JSON + optional redirect).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('auth.email_not_verified')], 403)
            : redirect()->guest('/login');
    }
}
