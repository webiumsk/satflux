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
        'base_url' => env('BTCPAY_BASE_URL', 'https://satflux.org'),
        'api_key' => env('BTCPAY_API_KEY'),
        'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
        'subscription_success_url' => env('SUBSCRIPTION_SUCCESS_URL'),
        'subscription_cancel_url' => env('SUBSCRIPTION_CANCEL_URL'),
        'allow_guest_subscriptions' => env('ALLOW_GUEST_SUBSCRIPTIONS', false),
        'subscription_store_id' => env('SUBSCRIPTION_STORE_ID', 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY'),
        'subscription_offering_id' => env('SUBSCRIPTION_OFFERING_ID', 'offering_GpWCnNRm6W9qqmgwdC'),
        'subscription_plans' => [
            'pro' => env('SUBSCRIPTION_PLAN_PRO_ID', 'plan_9UQMqk4vbAFyQinRpL'),
            'enterprise' => env('SUBSCRIPTION_PLAN_ENTERPRISE_ID'),
        ],
        // Note: Grace period is configured per plan in BTCPay Server, not here
    ],

    'discord' => [
        'support_webhook_url' => env('SUPPORT_DISCORD_WEBHOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | BTCPay Config Bot (Lightning setup automation)
    |--------------------------------------------------------------------------
    | Bot that automates BTCPay Lightning setup when wallet connection needs support.
    | Requires: panel bot token (create via tinker), BTCPay login, Node.js + Playwright.
    */
    'btcpay_config_bot' => [
        'enabled' => ! empty(env('BTCPAY_BOT_EMAIL')) && ! empty(env('PANEL_BOT_TOKEN')),
        'use_job' => env('BTCPAY_BOT_USE_JOB', false), // true = Laravel job in Docker; false = use poller on host
        // Use BTCPAY_BOT_PANEL_URL if panel runs on host and APP_URL is localhost (container can't reach host via localhost)
        'panel_url' => rtrim(env('BTCPAY_BOT_PANEL_URL', env('APP_URL', '')), '/'),
        'panel_token' => env('PANEL_BOT_TOKEN'),
        'panel_password' => env('PANEL_BOT_PASSWORD'),
        'btcpay_base_url' => rtrim(env('BTCPAY_BASE_URL', ''), '/'),
        'btcpay_email' => env('BTCPAY_BOT_EMAIL'),
        'btcpay_password' => env('BTCPAY_BOT_PASSWORD'),
        // Use /tmp by default so bot can write when running in Docker (storage/ may not be writable by www-data)
        'log_file' => env('BTCPAY_BOT_LOG_FILE', '/tmp/btcpay-config-bot.log'),
        'headless' => env('BTCPAY_BOT_HEADLESS', 'true') !== 'false',
    ],

];








