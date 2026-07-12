<?php

namespace App\Support;

/**
 * Boot-time configuration validation (P1 phase 7). Production must not serve
 * traffic with the security-critical configuration missing - the CSP
 * fail-closed guard set the pattern, this extends it to the core keys.
 *
 * Pure: reads the given config array so tests can exercise it without
 * touching the container.
 */
class ProductionConfigValidator
{
    /**
     * Config keys that MUST be non-empty in production, with the env name
     * used in the error message.
     *
     * @var array<string, string>
     */
    public const REQUIRED = [
        'app.key' => 'APP_KEY',
        'services.btcpay.base_url' => 'BTCPAY_BASE_URL',
        'services.btcpay.api_key' => 'BTCPAY_API_KEY',
        'sanctum.stateful' => 'SANCTUM_STATEFUL_DOMAINS',
    ];

    /**
     * @param  callable(string): mixed  $config  config accessor (key -> value)
     * @return list<string> env names of missing required values
     */
    public static function missing(callable $config): array
    {
        $missing = [];
        foreach (self::REQUIRED as $key => $envName) {
            $value = $config($key);
            $empty = $value === null
                || $value === ''
                || (is_array($value) && $value === []);
            if ($empty) {
                $missing[] = $envName;
            }
        }

        return $missing;
    }
}
