<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed locale keys for JSON columns
    |--------------------------------------------------------------------------
    | Used when querying JSON columns (e.g. question->>'en') to avoid SQL
    | injection. Only these keys may be used in whereRaw() for JSON access.
    */
    'json_locale_keys' => ['en', 'es', 'sk'],
];
