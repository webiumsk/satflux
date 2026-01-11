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
        $storeId = $request->route('store');

        if (!$storeId) {
            abort(404, 'Store not found');
        }

        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Add the store to the request for use in controllers
        $request->merge(['store' => $store]);
        $request->setRouteResolver(function () use ($store, $request) {
            $route = $request->route();
            $route->setParameter('store', $store);
            return $route;
        });

        return $next($request);
    }
}

