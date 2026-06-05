<?php

namespace App\Support\Invoicing;

/**
 * Maps legacy Slovak placeholder tokens to canonical English tokens.
 * Resolvers accept both; UI and new defaults use English only.
 */
final class PlaceholderLegacyAliases
{
    /**
     * @var array<string, string> legacy token => canonical English token
     */
    private const LEGACY_TO_CANONICAL = [
        // Recurring profile placeholders
        '#CISLOFAKTURY#' => '#INVOICE_NUMBER#',
        '#VAR#' => '#VARIABLE_SYMBOL#',
        '#DEN#' => '#DAY#',
        '#TYZDEN#' => '#WEEK#',
        '#MESIAC#' => '#MONTH#',
        '#MESIAC_SLOVOM#' => '#MONTH_NAME#',
        '#PREDOSLY_MESIAC#' => '#PREVIOUS_MONTH#',
        '#ROK#' => '#YEAR#',
        '#NASLEDOVNY_ROK#' => '#NEXT_YEAR#',
        // PDF filename + email placeholders
        '#NAZOV#' => '#TITLE#',
        '#TYP#' => '#TYPE#',
        '#FIRMA#' => '#COMPANY#',
        '#CISLO#' => '#NUMBER#',
        '#KLIENT#' => '#CLIENT#',
        '#VYSTAVENE#' => '#ISSUE_DATE#',
        '#SUMA#' => '#AMOUNT#',
        '#MENA#' => '#CURRENCY#',
        '#MOJA_FIRMA#' => '#MY_COMPANY#',
        '#MENO#' => '#SENDER_NAME#',
        '#NAZOV_ODBERATELA#' => '#CLIENT_NAME#',
        '#CISLO_ZAL#' => '#PROFORMA_NUMBER#',
        '#OBJEDNAVKA#' => '#ORDER_NUMBER#',
        '#DODANIE#' => '#DELIVERY_DATE#',
        '#PLATI_DO#' => '#VALID_UNTIL#',
        '#POZNAMKA_NAD#' => '#NOTE_ABOVE#',
        '#UHRADENA_SUMA#' => '#PAID_AMOUNT#',
        '#POSLEDNA_UHRADA#' => '#LAST_PAYMENT#',
        '#SPLATNOST#' => '#DUE_DATE#',
        '#FORMA_UHRADY#' => '#PAYMENT_METHOD#',
        '#UCET#' => '#ACCOUNT#',
        '#KONSTANTNY#' => '#CONSTANT_SYMBOL#',
        '#SPECIFICKY#' => '#SPECIFIC_SYMBOL#',
        '#ONLINE_PLATBA#' => '#ONLINE_PAYMENT#',
    ];

    /**
     * @param  array<string, string>  $canonicalReplacements  keyed by English tokens
     * @return array<string, string>
     */
    public static function merge(array $canonicalReplacements): array
    {
        $merged = $canonicalReplacements;

        foreach (self::LEGACY_TO_CANONICAL as $legacy => $canonical) {
            if (isset($canonicalReplacements[$canonical])) {
                $merged[$legacy] = $canonicalReplacements[$canonical];
            }
        }

        return $merged;
    }
}
