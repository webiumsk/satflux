<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    | CSP served by App\Http\Middleware\SetSecurityHeaders.
    |
    | Fail-closed in production: CSP defaults to ENABLED when APP_ENV=production,
    | so a deploy that forgets the flag still ships a policy. Explicitly setting
    | CSP_ENABLED=false in production makes the middleware fail loudly (500 +
    | log) rather than silently serving invoicing pages - which hold an account
    | mnemonic in browser storage - without script/connect restrictions.
    |
    | Rollout: set CSP_REPORT_ONLY=true first, watch the browser console /
    | report endpoint while clicking through the app (app load, Evolu relay
    | sync, PDF, BTCPay, WooCommerce), then flip CSP_REPORT_ONLY=false.
    |
    | Local dev keeps CSP off: the Vite dev server (@vite/client, HMR websocket)
    | runs on another origin and would violate script-src 'self'.
    |
    | connect-src is an explicit allowlist (no bare https:/wss:) built from the
    | origins the app actually talks to. Add origins via the *_url envs below;
    | connect_src_extra takes a space-separated list for anything else.
    */

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', env('APP_ENV') === 'production'),
        'report_only' => (bool) env('CSP_REPORT_ONLY', true),

        // Evolu sync relay websocket origin (backend-readable copy of the Vite
        // VITE_EVOLU_RELAY_URL the client uses). Set in production when relay
        // sync is enabled, otherwise the websocket is blocked by connect-src.
        'evolu_relay_url' => env('CSP_EVOLU_RELAY_URL', env('EVOLU_RELAY_URL')),

        // Extra connect-src origins (space-separated), e.g. an external Reverb host.
        'connect_src_extra' => (string) env('CSP_CONNECT_SRC_EXTRA', ''),
    ],

];
