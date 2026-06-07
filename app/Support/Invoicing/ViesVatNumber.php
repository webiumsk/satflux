<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;

/**
 * Parse and normalize EU VAT numbers for VIES requests.
 */
final class ViesVatNumber
{
    /**
     * @return array{country_code: string, vat_number: string}|null
     */
    public static function parse(string $raw, ?string $defaultCountryCode = null): ?array
    {
        $normalized = strtoupper(preg_replace('/[\s.\-]/', '', trim($raw)) ?? '');

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^([A-Z]{2})([A-Z0-9]{2,12})$/', $normalized, $matches)) {
            return [
                'country_code' => $matches[1],
                'vat_number' => $matches[2],
            ];
        }

        if ($defaultCountryCode !== null && preg_match('/^[0-9A-Z]{2,12}$/', $normalized)) {
            return [
                'country_code' => strtoupper($defaultCountryCode),
                'vat_number' => $normalized,
            ];
        }

        return null;
    }

    public static function defaultCountryFromJurisdiction(?CompanyJurisdiction $jurisdiction): ?string
    {
        return match ($jurisdiction) {
            CompanyJurisdiction::EuSk => 'SK',
            CompanyJurisdiction::EuCz => 'CZ',
            CompanyJurisdiction::Uk => 'GB',
            CompanyJurisdiction::EuOther => null,
            default => null,
        };
    }
}
