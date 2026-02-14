<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan feature keys (single source of truth)
    |--------------------------------------------------------------------------
    | Translation keys live in resources/js/locales: plans.features.<key>
    | Limits (max_stores, max_ln_addresses, max_api_keys) are in DB / SubscriptionPlanSeeder.
    */

    'free' => [
        'feature_keys' => [
            'store_1',
            'pos_unlimited',
            'ln_addresses_2',
            'api_key_1',
            'tickets_1',
            'manual_csv',
            'basic_stats',
            'no_tx_fees',
        ],
    ],

    'pro' => [
        'feature_keys' => [
            'stores_3',
            'pos_unlimited',
            'ln_unlimited',
            'api_keys_3',
            'tickets_3',
            'manual_auto_csv',
            'advanced_stats',
            'custom_branding',
            'priority_support',
        ],
    ],

    'enterprise' => [
        'feature_keys' => [
            'stores_unlimited',
            'tickets_unlimited',
            'webhooks',
            'multi_user_roles',
            'custom_reporting_formats',
            'integration_support',
            'pos_cash_card',
        ],
    ],

];
