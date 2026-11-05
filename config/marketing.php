<?php

return [
    // Refresh Laravel's configuration cache and reload the document to switch.
    'homepage_variant' => env('HOMEPAGE_VARIANT', 'legacy'),

    // Optional public demonstration terminal; loaded only after an explicit click.
    // Its origin must already be allowed by the existing CSP frame-src policy.
    'demo_pos_url' => env('HOMEPAGE_DEMO_POS_URL', ''),
];
