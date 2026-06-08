<?php

return [

    'enabled' => (bool) env('BANK_INBOUND_ENABLED', false),

    /** Shared secret for manual/curl POST /api/webhooks/bank-inbound (JSON body). */
    'webhook_secret' => env('BANK_INBOUND_WEBHOOK_SECRET'),

    /** Mailgun inbound route signing key (Domain settings → Webhooks). */
    'mailgun_webhook_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),

    'domain' => env('BANK_INBOUND_DOMAIN', 'payments.satflux.io'),

    'address_prefix' => env('BANK_INBOUND_ADDRESS_PREFIX', 'pay'),

    /** Slovak banks accept at most 50 chars for b-mail notification addresses. */
    'max_address_length' => (int) env('BANK_INBOUND_MAX_ADDRESS_LENGTH', 50),

    /**
     * Reject messages that look forwarded (SuperFaktura-style requirement).
     */
    'reject_forwarded' => (bool) env('BANK_INBOUND_REJECT_FORWARDED', true),

];
