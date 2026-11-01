<?php

return [
    'enabled' => (bool) env('BANK_MAIL_CAPTURE_ENABLED', false),
    'redis_connection' => env('BANK_MAIL_CAPTURE_REDIS_CONNECTION', 'cache'),
];
