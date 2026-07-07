<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    | Opt-in CSP served by App\Http\Middleware\SetSecurityHeaders. Rollout:
    | enable with CSP_REPORT_ONLY=true first, watch the browser console /
    | report endpoint for violations while clicking through the app, then
    | flip CSP_REPORT_ONLY=false to enforce.
    |
    | The policy is deliberately permissive where users control the resource
    | (crowdfund images, Evolu relay websockets): img-src https:, connect-src
    | https:/wss:. The main XSS mitigation is script-src 'self' (no inline or
    | eval'd JS) and object-src 'none' - localStorage holds an account
    | mnemonic, so blocking foreign script execution is what matters most.
    |
    | Note: CSP_ENABLED stays false for local dev - the Vite dev server
    | (@vite/client, HMR websocket) runs on another origin and would violate
    | script-src 'self'.
    */

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', false),
        'report_only' => (bool) env('CSP_REPORT_ONLY', true),
    ],

];
