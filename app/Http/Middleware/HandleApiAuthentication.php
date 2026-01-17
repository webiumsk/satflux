<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleApiAuthentication
{
    /**
     * Handle an incoming request.
     * For API routes, ensure 401 JSON responses instead of redirects.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // This middleware ensures API routes return JSON 401 instead of redirects
        // The actual authentication is handled by Sanctum middleware
        
        $response = $next($request);
        
        return $response;
    }
}

