<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription pricing (single source of truth)
    |--------------------------------------------------------------------------
    | Change prices here only. Used by GET /api/pricing, Landing and Profile.
    | All amounts in sats.
    */

    'free' => [
        'sats_per_year' => 0,
    ],

    'pro' => [
        'sats_per_year' => 99_000,
        'sats_per_month_display' => 16_500, // Shown as "16,500 sats/month (paid yearly)"
    ],

    'enterprise' => [
        // No fixed price – contact sales
    ],

];
