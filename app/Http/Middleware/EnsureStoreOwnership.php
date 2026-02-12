<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->route('store');

        if (!$store instanceof Store) {
            abort(404, 'Store not found');
        }

        // Owner, admin and support can access the store (admin/support have same access as owner for management)
        $user = $request->user();
        if ($store->user_id !== $user->id && !$user->isSupport()) {
            abort(403, 'Unauthorized access to store');
        }

        return $next($request);
    }
}





