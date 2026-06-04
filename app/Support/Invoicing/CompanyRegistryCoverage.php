<?php

namespace App\Support\Invoicing;

/**
 * Which countries use subjekt.sk, OpenRegistry proxy, or manual entry only.
 *
 * @see docs/COMPANY_REGISTRY_COVERAGE.md
 */
class CompanyRegistryCoverage
{
    /** @var list<string> */
    public const SUBJEKT = ['sk', 'cz'];

    /** @var list<string> ISO 3166-1 alpha-2 (OpenRegistry jurisdiction codes). */
    public const OPEN_REGISTRY = [
        'pl', 'fr', 'it', 'es', 'nl', 'be', 'ch', 'ie', 'gb', 'fi', 'cy', 'hk',
    ];

    /** @var list<string> Manual entry; VIES may still apply for EU VAT IDs. */
    public const MANUAL_VIES = ['de', 'at', 'hu', 'pt'];

    /** @var list<string> Offshore / other - manual only. */
    public const MANUAL_OFFSHORE = [
        'us', 'gi', 'pa', 'ky', 'vg', 'bm', 'ae', 'sg', 'lu', 'mt', 'ee', 'lv', 'lt', 'ro', 'bg', 'hr', 'si',
    ];

    public static function normalize(string $country): string
    {
        $c = strtolower(trim($country));
        if ($c === 'czech' || $c === 'cesko' || $c === 'česko') {
            return 'cz';
        }
        if ($c === 'slovakia' || $c === 'slovensko') {
            return 'sk';
        }
        if ($c === 'uk' || $c === 'ukr') {
            return 'gb';
        }

        return $c;
    }

    public static function provider(string $country): string
    {
        $c = self::normalize($country);
        if (in_array($c, self::SUBJEKT, true)) {
            return 'subjekt';
        }
        if (in_array($c, self::OPEN_REGISTRY, true)) {
            return 'openregistry';
        }

        return 'manual';
    }

    public static function supportsAutocomplete(string $country): bool
    {
        return self::provider($country) !== 'manual';
    }

    /**
     * @return list<string>
     */
    public static function searchCountries(): array
    {
        return array_values(array_unique(array_merge(self::SUBJEKT, self::OPEN_REGISTRY)));
    }

    /**
     * @return list<string>
     */
    public static function allCountryCodes(): array
    {
        return array_values(array_unique(array_map(
            fn (array $o) => $o['value'],
            self::optionsForFrontend()
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public static function metaForApi(): array
    {
        return [
            'subjekt' => self::SUBJEKT,
            'openregistry' => self::OPEN_REGISTRY,
            'manual_vies' => self::MANUAL_VIES,
            'manual_offshore' => self::MANUAL_OFFSHORE,
            'openregistry_configured' => (bool) config('services.openregistry.enabled', true),
        ];
    }

    /**
     * @return list<array{value: string, group: string, provider: string, label: string}>
     */
    public static function optionsForFrontend(): array
    {
        $groups = [
            'central_eu' => [
                ['value' => 'sk', 'label' => 'SK'],
                ['value' => 'cz', 'label' => 'CZ'],
                ['value' => 'pl', 'label' => 'PL'],
            ],
            'western_eu' => [
                ['value' => 'fr', 'label' => 'FR'],
                ['value' => 'nl', 'label' => 'NL'],
                ['value' => 'be', 'label' => 'BE'],
                ['value' => 'es', 'label' => 'ES'],
                ['value' => 'it', 'label' => 'IT'],
                ['value' => 'de', 'label' => 'DE'],
                ['value' => 'at', 'label' => 'AT'],
                ['value' => 'pt', 'label' => 'PT'],
                ['value' => 'hu', 'label' => 'HU'],
            ],
            'other_eu_eea' => [
                ['value' => 'ch', 'label' => 'CH'],
                ['value' => 'ie', 'label' => 'IE'],
                ['value' => 'fi', 'label' => 'FI'],
                ['value' => 'cy', 'label' => 'CY'],
            ],
            'uk_offshore' => [
                ['value' => 'gb', 'label' => 'GB'],
                ['value' => 'gi', 'label' => 'GI'],
                ['value' => 'ky', 'label' => 'KY'],
                ['value' => 'pa', 'label' => 'PA'],
            ],
            'americas' => [
                ['value' => 'us', 'label' => 'US'],
            ],
            'asia' => [
                ['value' => 'hk', 'label' => 'HK'],
            ],
        ];

        $out = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $item) {
                $out[] = [
                    'value' => $item['value'],
                    'label' => $item['label'],
                    'group' => $group,
                    'provider' => self::provider($item['value']),
                    'autocomplete' => self::supportsAutocomplete($item['value']),
                ];
            }
        }

        return $out;
    }
}
