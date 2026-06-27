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

    /*
    | Maximum number of stores per guest for which we call BTCPay listInvoices
    | during idle checks (avoids many sequential API calls for unusual users).
    */
    'max_stores_check' => (int) env('GUEST_PURGE_MAX_STORES_CHECK', 10),

    /*
    |--------------------------------------------------------------------------
    | Seed-first registration
    |--------------------------------------------------------------------------
    |
    | When true, direct email/password registration is disabled. New users start
    | with a recovery phrase (guest provisioning) and may add email later.
    */
    'seed_first_registration' => filter_var(env('SEED_FIRST_REGISTRATION', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Guest upgrade without password
    |--------------------------------------------------------------------------
    |
    | When true (default follows seed_first_registration), Guest → Free upgrade
    | requires only a real email + verification; password is not collected.
    */
    'upgrade_email_only' => filter_var(
        env('GUEST_UPGRADE_EMAIL_ONLY', env('SEED_FIRST_REGISTRATION', false)),
        FILTER_VALIDATE_BOOLEAN,
    ),

];
