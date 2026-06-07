<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slovak e-faktura (digitálny poštár / Peppol) - global switch
    |--------------------------------------------------------------------------
    | Per-company credentials and auto-send live in company app_settings.
    | See docs/SK_EFAKTURA.md.
    */

    'enabled' => filter_var(env('EFAKTURA_ENABLED', false), FILTER_VALIDATE_BOOL),

    'mandatory_from' => '2027-01-01',

    'default_provider' => env('EFAKTURA_PROVIDER', 'sapi_sk'),

    'queue' => env('EFAKTURA_QUEUE', 'default'),

    'inbound_poll_limit' => (int) env('EFAKTURA_INBOUND_POLL_LIMIT', 20),

    'providers' => [
        'sapi_sk' => [
            // Optional global fallback for local dev; merchants set efaktura_sapi_base_url per company.
            'base_url' => rtrim((string) env('EFAKTURA_SAPI_BASE_URL', ''), '/'),
            'token_path' => '/sapi/v1/auth/token',
            'send_path' => '/sapi/v1/document/send',
            'receive_path' => '/sapi/v1/document/receive',
            'receive_detail_path' => '/sapi/v1/document/receive/{id}',
            'acknowledge_path' => '/sapi/v1/document/receive/{id}/acknowledge',
            'timeout_seconds' => (int) env('EFAKTURA_SAPI_TIMEOUT', 30),
        ],
    ],

];
