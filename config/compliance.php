<?php

$coerceHttpsUrl = static function (?string $url, string $default): string {
    $value = is_string($url) && $url !== '' ? $url : $default;

    if (str_starts_with($value, 'http://')) {
        $value = 'https://'.substr($value, 8);
    }

    return $value;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Compliance screening (OFAC / sanctions)
    |--------------------------------------------------------------------------
    */

    'enabled' => env('COMPLIANCE_SCREENING_ENABLED', false),

    'geo_block_enabled' => env('COMPLIANCE_GEO_BLOCK_ENABLED', true),

    'list_screening_enabled' => env('COMPLIANCE_LIST_SCREENING_ENABLED', false),

    /**
     * When true, block registration if geo country cannot be resolved or list screening errors.
     */
    'fail_closed' => env('COMPLIANCE_FAIL_CLOSED', env('APP_ENV') === 'production'),

    /**
     * ISO 3166-1 alpha-2 codes subject to comprehensive sanctions (ToS 5a.2(a)).
     */
    'blocked_country_codes' => ['CU', 'IR', 'KP', 'SY'],

    /**
     * Optional override for local/testing (e.g. GEO_COUNTRY_OVERRIDE=IR).
     */
    'geo_country_override' => env('COMPLIANCE_GEO_COUNTRY_OVERRIDE'),

    /**
     * MaxMind GeoLite2 Country database path (download via compliance:update-geoip).
     */
    'maxmind_database_path' => env(
        'COMPLIANCE_MAXMIND_DATABASE_PATH',
        storage_path('app/compliance/GeoLite2-Country.mmdb')
    ),

    'maxmind_account_id' => env('MAXMIND_ACCOUNT_ID'),

    'maxmind_license_key' => env('MAXMIND_LICENSE_KEY'),

    'retention_years' => (int) env('COMPLIANCE_RETENTION_YEARS', 7),

    'ofac_sdn_url' => $coerceHttpsUrl(
        env('COMPLIANCE_OFAC_SDN_URL'),
        'https://www.treasury.gov/ofac/downloads/sdn.xml',
    ),

    'eu_sanctions_url' => env(
        'COMPLIANCE_EU_SANCTIONS_URL',
        'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList/content',
    ),

    'opensanctions_csv_url' => env(
        'COMPLIANCE_OPENSANCTIONS_CSV_URL',
        'https://data.opensanctions.org/datasets/latest/sanctions/targets.simple.csv',
    ),

];
