<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Handle an incoming request. Add cache-prevention headers to Inertia
     * JSON responses so the browser does not serve a cached JSON document
     * when the user navigates back (which would show raw JSON instead of the app).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        $response->headers->set('Vary', 'X-Inertia');

        return $response;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authUser = $user ? [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'name' => $user->name,
            'role' => $user->role ?? null,
            'plan' => (function () use ($user) {
                $plan = $user->currentSubscriptionPlan();
                return $plan ? [
                    'code' => $plan->code,
                    'name' => $plan->display_name,
                    'max_stores' => $plan->max_stores,
                    'max_api_keys' => $plan->max_api_keys,
                    'max_ln_addresses' => $user->getMaxLightningAddresses(),
                    'features' => $plan->features ?? [],
                ] : null;
            })(),
        ] : null;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $authUser,
            ],
            'app' => [
                'version' => config('app.version', '1.0.0'),
                'name' => config('app.name', 'satflux.io'),
                'lnurlAuthEnabled' => filter_var(env('LNURL_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            ],
            'flash' => [
                'message' => $request->session()->get('message'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            'locale' => $request->getLocale(),
            'errors' => $request->session()->get('errors')
                ? $request->session()->get('errors')->getBag('default')->getMessages()
                : (object) [],
        ]);
    }
}
