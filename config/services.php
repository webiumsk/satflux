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

    'nwc_connector' => [
        'base_url' => env('NWC_CONNECTOR_URL', 'http://nwc-connector:8082'),
        'api_key' => env('NWC_PANEL_API_KEY'),
    ],

];








