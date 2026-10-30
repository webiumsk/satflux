<?php

namespace App\Support\Invoicing;

final class BankAccountIdentifier
{
    /** Full receiving accounts supported by the Slovak bank-mail parsers. */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $iban = strtoupper(preg_replace('/\s+/u', '', trim($value)) ?? '');
        if (preg_match('/\A(?:SK|CZ)[0-9]{22}\z/D', $iban) !== 1) {
            return null;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_digit($character) ? $character : (string) (ord($character) - 55);
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1 ? $iban : null;
    }
}
