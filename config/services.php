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
        'base_url' => env('BTCPAY_BASE_URL', 'https://pay.dvadsatjeden.org'),
        'api_key' => env('BTCPAY_API_KEY'),
        'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
        'subscription_success_url' => env('SUBSCRIPTION_SUCCESS_URL'),
        'subscription_cancel_url' => env('SUBSCRIPTION_CANCEL_URL'),
        'allow_guest_subscriptions' => env('ALLOW_GUEST_SUBSCRIPTIONS', false),
        'subscription_store_id' => env('SUBSCRIPTION_STORE_ID', 'REDACTED_BTCPAY_STORE_ID'),
        'subscription_offering_id' => env('SUBSCRIPTION_OFFERING_ID', 'offering_GpWCnNRm6W9qqmgwdC'),
        'subscription_plans' => [
            'pro' => env('SUBSCRIPTION_PLAN_PRO_ID', 'plan_9UQMqk4vbAFyQinRpL'),
            'enterprise' => env('SUBSCRIPTION_PLAN_ENTERPRISE_ID'),
        ],
    ],

];








