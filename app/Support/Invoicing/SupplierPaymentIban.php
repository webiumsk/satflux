<?php

namespace App\Support\Invoicing;

/** Supplier payment validation shared with the browser's pinned IBAN registry. */
final class SupplierPaymentIban
{
    /** @var array<string, int>|null */
    private static ?array $lengths = null;

    public static function normalize(string $value): ?string
    {
        $iban = strtoupper(str_replace(' ', '', $value));
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)) {
            return null;
        }
        $length = self::countryLengths()[substr($iban, 0, 2)] ?? null;
        if ($length === null || strlen($iban) !== $length) {
            return null;
        }

        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1 ? $iban : null;
    }

    /** @return array<string, int> */
    private static function countryLengths(): array
    {
        if (self::$lengths !== null) {
            return self::$lengths;
        }
        $path = dirname(__DIR__, 3).'/resources/data/iban-lengths.json';
        $contents = is_readable($path) ? file_get_contents($path) : false;
        $registry = $contents !== false ? json_decode($contents, true) : null;
        $lengths = [];
        if (is_array($registry)) {
            foreach ($registry as $country => $length) {
                if (is_string($country) && preg_match('/^[A-Z]{2}$/D', $country)
                    && is_int($length) && $length >= 15 && $length <= 34) {
                    $lengths[$country] = $length;
                }
            }
        }

        return self::$lengths = $lengths;
    }
}
