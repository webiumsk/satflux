<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\PlaceholderLegacyAliases;
use Carbon\CarbonInterface;

/**
 * Resolves SuperFaktúra-style placeholders in recurring profile text fields.
 */
final class RecurringPlaceholderResolver
{
    /**
     * @return list<string>
     */
    public static function knownTokens(): array
    {
        return [
            '#INVOICE_NUMBER#',
            '#VARIABLE_SYMBOL#',
            '#DAY#',
            '#WEEK#',
            '#MONTH#',
            '#MONTH_NAME#',
            '#PREVIOUS_MONTH#',
            '#YEAR#',
            '#NEXT_YEAR#',
        ];
    }

    public function resolve(?string $text, CarbonInterface $issueDate, ?string $documentNumber = null, ?string $variableSymbol = null): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $locale = 'sk_SK';
        $monthNames = [
            1 => 'január', 2 => 'február', 3 => 'marec', 4 => 'apríl',
            5 => 'máj', 6 => 'jún', 7 => 'júl', 8 => 'august',
            9 => 'september', 10 => 'október', 11 => 'november', 12 => 'december',
        ];

        $prevMonth = $issueDate->copy()->subMonth();
        $number = $documentNumber ?? '';
        $vs = BankSymbolNormalizer::variableSymbol($variableSymbol)
            ?? BankSymbolNormalizer::variableSymbol($number)
            ?? '';

        $map = PlaceholderLegacyAliases::merge([
            '#INVOICE_NUMBER#' => $number,
            '#VARIABLE_SYMBOL#' => $vs,
            '#DAY#' => $issueDate->format('d'),
            '#WEEK#' => $issueDate->format('W'),
            '#MONTH#' => $issueDate->format('m'),
            '#MONTH_NAME#' => $monthNames[(int) $issueDate->format('n')] ?? $issueDate->format('m'),
            '#PREVIOUS_MONTH#' => $prevMonth->format('m'),
            '#YEAR#' => $issueDate->format('Y'),
            '#NEXT_YEAR#' => $issueDate->copy()->addYear()->format('Y'),
        ]);

        return str_replace(array_keys($map), array_values($map), $text);
    }
}
