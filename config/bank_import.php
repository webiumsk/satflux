<?php

return [

    'storage_disk' => 'local',

    'storage_directory' => 'bank-imports',

    /** Retention for uploaded statement files (days). */
    'file_retention_days' => (int) env('BANK_IMPORT_FILE_RETENTION_DAYS', 30),

    'amount_tolerance' => (float) env('BANK_MATCH_AMOUNT_TOLERANCE', 0.01),

    /**
     * CSV column mapping profiles (header substring, case-insensitive).
     */
    'csv_profiles' => [
        'generic' => [
            'date' => ['datum', 'date', 'booking', 'booked', 'dátum'],
            'amount' => ['suma', 'amount', 'částka', 'castka', 'hodnota'],
            'currency' => ['mena', 'currency', 'ccy'],
            'variable_symbol' => ['vs', 'variabilny', 'variabilný', 'variable'],
            'constant_symbol' => ['ks', 'konstantny', 'konstantný', 'constant'],
            'specific_symbol' => ['ss', 'specificky', 'specifický', 'specific'],
            'counterparty' => ['partner', 'protistrana', 'name', 'názov', 'nazov', 'popis'],
            'reference' => ['referencia', 'reference', 'poznámka', 'poznamka', 'note'],
            'direction' => ['typ', 'type', 'smer', 'direction'],
        ],
        'tatra' => [
            'date' => ['datum zauctovania', 'dátum zaúčtovania', 'datum'],
            'amount' => ['suma', 'amount'],
            'variable_symbol' => ['vs', 'variabilny symbol'],
            'counterparty' => ['nazov protistrany', 'názov protistrany'],
            'reference' => ['informacia pre prijemcu', 'informácia pre príjemcu'],
        ],
    ],

    'default_csv_profile' => 'generic',

];
