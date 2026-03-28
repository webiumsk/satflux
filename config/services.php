<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'btcpay' => [
        'base_url' => env('BTCPAY_BASE_URL', 'http://127.0.0.1:14142'),
        'api_key' => env('BTCPAY_API_KEY'),
        'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
        'subscription_success_url' => env('SUBSCRIPTION_SUCCESS_URL'),
        'subscription_cancel_url' => env('SUBSCRIPTION_CANCEL_URL'),
        'allow_guest_subscriptions' => env('ALLOW_GUEST_SUBSCRIPTIONS', false),
        'subscription_store_id' => env('SUBSCRIPTION_STORE_ID'),
        'subscription_offering_id' => env('SUBSCRIPTION_OFFERING_ID'),
        'subscription_plans' => [
            'pro' => env('SUBSCRIPTION_PLAN_PRO_ID'),
            'enterprise' => env('SUBSCRIPTION_PLAN_ENTERPRISE_ID'),
        ],
        // Lightning Address host in the UI (user@host). Explicit env, else hostname of BTCPAY_BASE_URL (same default as base_url).
        'lightning_address_domain' => (static function (): string {
            $explicit = env('BTCPAY_LIGHTNING_ADDRESS_DOMAIN');
            if (is_string($explicit) && $explicit !== '') {
                return $explicit;
            }
            $base = (string) env('BTCPAY_BASE_URL', 'http://127.0.0.1:14142');
            $host = parse_url($base, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : '';
        })(),
        // Note: Grace period is configured per plan in BTCPay Server, not here
    ],

    'matomo' => [
        'url' => env('MATOMO_URL'),
        'site_id' => env('MATOMO_SITE_ID'),
    ],

    'discord' => [
        'support_webhook_url' => env('SUPPORT_DISCORD_WEBHOOK_URL'),
    ],

    'lnurl_auth' => [
        'enabled' => filter_var(env('LNURL_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'domain' => env('LNURL_AUTH_DOMAIN'),
    ],

    'nostr_auth' => [
        'enabled' => filter_var(env('NOSTR_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'challenge_ttl_seconds' => (int) env('NOSTR_AUTH_CHALLENGE_TTL', 300),
    ],

];








