<?php

namespace App\Http\Middleware;

use App\Models\StoreIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWooCommerceIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = trim(substr($header, 7));
        $integration = StoreIntegration::findByToken($token);
        if (! $integration) {
            return response()->json(['message' => 'Invalid integration token'], 401);
        }

        $integration->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('store_integration', $integration);
        $request->attributes->set('integration_store', $integration->store);

        return $next($request);
    }
}
