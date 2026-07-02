<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    | The SPA is served by this same Laravel app, so no cross-origin browser
    | client needs the API - origins are restricted to APP_URL instead of the
    | framework default '*'. External browser consumers (if ever added) must be
    | listed here explicitly. Server-to-server callers (BTCPay webhooks,
    | WooCommerce, Mailgun) are unaffected - CORS only constrains browsers.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('APP_URL'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Sanctum cookie auth is same-origin; credentialed cross-origin requests
    // are deliberately not supported.
    'supports_credentials' => false,

];
