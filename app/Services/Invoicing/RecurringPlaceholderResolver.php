<?php

namespace App\Services\Invoicing;

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
            '#CISLOFAKTURY#',
            '#VAR#',
            '#DEN#',
            '#TYZDEN#',
            '#MESIAC#',
            '#MESIAC_SLOVOM#',
            '#PREDOSLY_MESIAC#',
            '#ROK#',
            '#NASLEDOVNY_ROK#',
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
        $vs = $variableSymbol ?? $number;

        $map = [
            '#CISLOFAKTURY#' => $number,
            '#VAR#' => $vs,
            '#DEN#' => $issueDate->format('d'),
            '#TYZDEN#' => $issueDate->format('W'),
            '#MESIAC#' => $issueDate->format('m'),
            '#MESIAC_SLOVOM#' => $monthNames[(int) $issueDate->format('n')] ?? $issueDate->format('m'),
            '#PREDOSLY_MESIAC#' => $prevMonth->format('m'),
            '#ROK#' => $issueDate->format('Y'),
            '#NASLEDOVNY_ROK#' => $issueDate->copy()->addYear()->format('Y'),
        ];

        return str_replace(array_keys($map), array_values($map), $text);
    }
}
