<?php

namespace App\Support\Invoicing;

final class IsoCountryCode
{
    /**
     * Normalize stored country labels to ISO 3166-1 alpha-2 for API validation.
     */
    public static function normalize(?string $country): ?string
    {
        $text = trim((string) ($country ?? ''));
        if ($text === '') {
            return null;
        }

        if (strlen($text) === 2) {
            return strtoupper($text);
        }

        $key = mb_strtolower($text);

        return match ($key) {
            'slovensko', 'slovakia' => 'SK',
            'česko', 'cesko', 'czech republic', 'czechia' => 'CZ',
            'rakúsko', 'rakusko', 'austria', 'österreich', 'osterreich' => 'AT',
            'maďarsko', 'madarsko', 'hungary', 'magyarország', 'magyarorszag' => 'HU',
            'poľsko', 'polsko', 'poland', 'polska' => 'PL',
            'nemecko', 'germany', 'deutschland' => 'DE',
            'usa', 'united states', 'united states of america' => 'US',
            'united kingdom', 'great britain', 'england' => 'GB',
            default => null,
        };
    }
}
