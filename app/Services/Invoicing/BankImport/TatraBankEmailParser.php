<?php

namespace App\Services\Invoicing\BankImport;

use App\Enums\BankTransactionDirection;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;

/**
 * Parses Tatra banka account movement notification emails (plaintext/HTML stripped).
 */
class TatraBankEmailParser implements BankNotificationParser
{
    public function supports(string $from, string $subject, string $body): bool
    {
        $haystack = strtolower($from.' '.$subject.' '.$body);

        return str_contains($haystack, 'tatrabanka')
            || str_contains($haystack, 'tatra banka')
            || str_contains($subject, 'obrat');
    }

    public function parse(string $from, string $subject, string $body): array
    {
        $text = strip_tags(html_entity_decode($body));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $amount = $this->matchAmount($text);
        if ($amount === null) {
            return [];
        }

        $direction = $amount >= 0
            ? BankTransactionDirection::Credit
            : BankTransactionDirection::Debit;

        $vs = null;
        if (preg_match('/(?:VS|variabiln[ýy]\s*symbol)[:\s]*(\d+)/iu', $text, $m)) {
            $vs = BankSymbolNormalizer::variableSymbol($m[1]);
        }

        $bookedAt = Carbon::now();
        if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4})/u', $text, $dm)) {
            $bookedAt = Carbon::createFromFormat('d.m.Y', $dm[1]) ?: $bookedAt;
        }

        $counterparty = null;
        if (preg_match('/protistrana[:\s]+([^|]+)/iu', $text, $pm)) {
            $counterparty = trim($pm[1]);
        }

        return [
            new ParsedBankTransaction(
                bookedAt: $bookedAt,
                amount: abs($amount),
                currency: $this->matchCurrency($text) ?? 'EUR',
                direction: $direction,
                variableSymbol: $vs,
                counterpartyName: $counterparty,
                reference: trim($subject) !== '' ? trim($subject) : null,
            ),
        ];
    }

    protected function matchAmount(string $text): ?float
    {
        if (preg_match('/(?:suma|amount|obrat)[:\s]*(-?\d+[.,]\d{2})/iu', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(-?\d+[.,]\d{2})\s*(?:EUR|€)/u', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    protected function matchCurrency(string $text): ?string
    {
        if (preg_match('/\b(EUR|USD|CZK|GBP)\b/u', $text, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
