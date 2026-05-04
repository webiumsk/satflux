<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inactive guest purge (scheduled + guests:purge-inactive)
    |--------------------------------------------------------------------------
    |
    | Guests are purged only when BOTH are true for the idle window:
    | - last_login_at is null or older than idle_days, and
    | - no BTCPay invoices created on any of the user's stores in that window.
    |
    */

    'purge_enabled' => (bool) env('GUEST_PURGE_ENABLED', false),

    'idle_days' => (int) env('GUEST_PURGE_IDLE_DAYS', 90),

    'batch_size' => (int) env('GUEST_PURGE_BATCH_SIZE', 50),

];
