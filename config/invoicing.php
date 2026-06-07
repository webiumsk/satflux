<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beta override for Pro company limit
    |--------------------------------------------------------------------------
    | When set (e.g. 5), Pro users can create up to this many invoicing companies
    | instead of the plan's max_companies in the database. Leave empty in production.
    */

    'beta_pro_max_companies' => env('INVOICING_BETA_PRO_MAX_COMPANIES') !== null && env('INVOICING_BETA_PRO_MAX_COMPANIES') !== ''
        ? (int) env('INVOICING_BETA_PRO_MAX_COMPANIES')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Expense ISDOC extract (paid feature, free trial)
    |--------------------------------------------------------------------------
    | Number of ISDOC extractions included per user. Enterprise plan feature
    | expense_isdoc_extract_unlimited removes the cap.
    */

    'expense_isdoc_extract_free_limit' => (int) env('INVOICING_EXPENSE_ISDOC_FREE_LIMIT', 20),

    /*
    |--------------------------------------------------------------------------
    | ISDOC extract packs (EUR incl. VAT, aligned with SuperFaktúra tiers)
    |--------------------------------------------------------------------------
    */

    'expense_isdoc_packs' => [
        ['credits' => 25, 'price_eur' => 11.07],
        ['credits' => 50, 'price_eur' => 18.45],
        ['credits' => 100, 'price_eur' => 28.29],
        ['credits' => 500, 'price_eur' => 92.25],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription billing (paid plan invoices in Invoicing)
    |--------------------------------------------------------------------------
    | When a subscription payment settles on SUBSCRIPTION_STORE_ID, a paid
    | business invoice is created in this company for the subscriber.
    */

    'subscription_billing' => [
        'company_id' => env('SUBSCRIPTION_BILLING_COMPANY_ID'),
        'eur_currency' => 'EUR',
        'line_names' => [
            'pro' => 'Satflux Pro - ročné predplatné',
            'enterprise' => 'Satflux Enterprise - ročné predplatné',
        ],
    ],

];
