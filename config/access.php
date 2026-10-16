<?php

return [
    // Enable only after the separately approved production readiness gate.
    'free_pro_enabled' => env('FREE_PRO_ENABLED', false),
    'policy_version' => 'free-pro-v1',
    // Ending promotional access must never reopen purchases implicitly.
    'pro_purchases_enabled' => env('PRO_PURCHASES_ENABLED', false),
    'company_slot_purchases_enabled' => env('COMPANY_SLOT_PURCHASES_ENABLED', false),
    'isdoc_pack_purchases_enabled' => env('ISDOC_PACK_PURCHASES_ENABLED', false),
];
