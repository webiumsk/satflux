<?php

return [

    'enabled' => (bool) env('BANK_INBOUND_ENABLED', false),

    /** Shared secret for POST /api/webhooks/bank-inbound */
    'webhook_secret' => env('BANK_INBOUND_WEBHOOK_SECRET'),

    'domain' => env('BANK_INBOUND_DOMAIN', 'payments.satflux.io'),

    'address_prefix' => env('BANK_INBOUND_ADDRESS_PREFIX', 'pay'),

    /**
     * Reject messages that look forwarded (SuperFaktura-style requirement).
     */
    'reject_forwarded' => (bool) env('BANK_INBOUND_REJECT_FORWARDED', true),

];
