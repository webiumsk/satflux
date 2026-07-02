<?php

return [

    'base_url' => env('WISE_API_BASE_URL', 'https://api.wise.com'),

    'sandbox_url' => env('WISE_API_SANDBOX_URL', 'https://api.sandbox.transferwise.com'),

    'use_sandbox' => (bool) env('WISE_USE_SANDBOX', false),

    /** Default statement lookback when from/to omitted (days). */
    'default_sync_days' => (int) env('WISE_DEFAULT_SYNC_DAYS', 30),

];
