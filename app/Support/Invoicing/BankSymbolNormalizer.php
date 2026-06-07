<?php

namespace App\Support\Invoicing;

final class BankSymbolNormalizer
{
    public static function variableSymbol(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    public static function constantSymbol(?string $value): ?string
    {
        return self::variableSymbol($value);
    }

    public static function specificSymbol(?string $value): ?string
    {
        return self::variableSymbol($value);
    }
}
