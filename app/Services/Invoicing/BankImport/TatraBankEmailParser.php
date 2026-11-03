<?php

namespace App\Services\Invoicing\BankImport;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Support\Invoicing\BankAccountIdentifier;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;

/**
 * Parses Tatra banka B-mail notification emails (classic multi-line and compact formats).
 */
class TatraBankEmailParser implements BankNotificationParser
{
    public function supports(string $from, string $subject, string $body): bool
    {
        $haystack = strtolower($from.' '.$subject.' '.$body);

        return str_contains($haystack, 'tatrabanka')
            || str_contains($haystack, 'tatra banka')
            || str_contains(strtolower($subject), 'obrat');
    }

    public function parse(string $from, string $subject, string $body): array
    {
        $text = $this->normalizeBody($body);
        $bankPrefix = $this->bankAuthoredPrefix($text);
        $greeting = '(?:V[aá][zž]en[yý]\s+klient,\s*)?';
        $classicHeader = preg_match('/\A'.$greeting.'[^\n]*\bbol zostatok Vasho uctu\b/iu', $bankPrefix) === 1;
        if (! $classicHeader && preg_match('/bol zostatok Vasho uctu/iu', $bankPrefix)) {
            return [];
        }
        $classicDirection = null;
        if ($classicHeader) {
            if (! preg_match('/\A'.$greeting.'\d{1,2}\.?\s*\d{1,2}\.?\s*\d{4}\s+\d{1,2}:\d{2}\s+bol zostatok Vasho uctu\s+\S+\s+(zvyseny|znizeny)\b/iu', $bankPrefix, $opening)) {
                // An incomplete classic opening cannot be a compact notification.
                return [];
            }
            $classicDirection = strtolower($opening[1]);
        }
        $isDebit = $classicHeader ? $classicDirection === 'znizeny'
            : app(BankTransactionDirectionGuesser::class)->fromAmountAndHints(
                $this->matchAmount($bankPrefix) ?? 0, $subject, $bankPrefix,
            ) === BankTransactionDirection::Debit;
        $debit = $isDebit ? $this->qualifiedDebit($text, $classicHeader) : null;
        if ($isDebit && $debit === null) {
            // An incomplete classic debit must not escape into compact parsing.
            return [];
        }
        // Select strict evidence using sender identity, never a body label.
        // Origin is independently authenticated before SEPA reporting.
        $evidence = $this->strictSepaEvidence($from, $body);
        $classic = $classicHeader ? $this->parseClassic($text, $subject, $evidence, $debit) : null;

        if ($classic !== null) {
            return [$classic];
        }
        if ($classicHeader) {
            return [];
        }

        $compact = $this->parseCompact($text, $subject, $debit);

        return $compact !== null ? [$compact] : [];
    }

    /** @return array{e2e: ?string, vs: ?string, identity: string}|null */
    private function strictSepaEvidence(string $from, string $body): ?array
    {
        $originVerifier = new BankEmailOriginVerifier;
        if ($originVerifier->matchesSender('tatrabanka.sk', $from)) {
            return (new TatraBankSepaEvidenceParser)->parse($body);
        }
        if ($originVerifier->matchesSender('slsp.sk', $from)) {
            return (new SlspBankSepaEvidenceParser)->parse($body);
        }

        return null;
    }

    protected function normalizeBody(string $body): string
    {
        $text = strip_tags(html_entity_decode($body));
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array{e2e: ?string, vs: ?string, identity: string}|null  $evidence
     * @param  array{amount: float, currency: string, date: Carbon, vs: ?string}|null  $debit
     */
    protected function parseClassic(string $text, string $subject, ?array $evidence = null, ?array $debit = null): ?ParsedBankTransaction
    {
        if (! preg_match(
            '/(\d{1,2}\.?\s*\d{1,2}\.?\s*\d{4}\s+\d{1,2}:\d{2})\s+bol zostatok Vasho uctu\s+(\S+)\s+(zvyseny|znizeny)\s+o\s+([\d\s,]+)\s+([A-Z]{3})/iu',
            $text,
            $main,
        )) {
            return null;
        }

        $amount = $debit['amount'] ?? (float) str_replace([' ', ','], ['', '.'], trim($main[4]));
        $currency = $debit['currency'] ?? strtoupper($main[5]);
        $direction = strtolower($main[3]) === 'znizeny'
            ? BankTransactionDirection::Debit
            : BankTransactionDirection::Credit;

        $bookedAt = $debit['date'] ?? $this->parseClassicDateTime($main[1]) ?? Carbon::now();

        $popis = $this->matchField($text, 'Popis transakcie');
        $referencia = $this->matchField($text, 'Referencia platitela');
        $informacia = $this->matchField($text, 'Informacia pre prijemcu');
        $protistrana = $this->matchField($text, 'Protistrana');

        [$variableSymbol, $constantSymbol, $specificSymbol, $referenciaName] = $this->parseReferenciaPlatitela($referencia);

        if ($debit !== null) {
            $variableSymbol = $debit['vs'];
        } elseif ($variableSymbol === null) {
            $variableSymbol = $this->inferVariableSymbol($text, $informacia, $referencia);
        }

        $counterparty = $this->resolveCounterpartyName(
            $protistrana,
            $referenciaName,
            $popis,
            $informacia,
            $subject,
            $text,
        );

        $reference = $this->resolveReference($informacia, $popis, $referencia, $subject, $text);

        // Bank authentication does not authenticate the payer's remittance as
        // an amount. Only the supported opening bank statement is evidence.
        $hasBankCreditHeader = preg_match(
            '/\A(?:V[aá][zž]en[yý]\s+klient,\s*)?\d{1,2}\.?\s*\d{1,2}\.?\s*\d{4}\s+\d{1,2}:\d{2}\s+bol zostatok Vasho uctu\s+\S+\s+(?:zvyseny|znizeny)\s+o\s+[\d\s,]+\s+[A-Z]{3}\.?[ \t]*(?:\n|$)/iu',
            $text,
        ) === 1;

        return new ParsedBankTransaction(
            bookedAt: $bookedAt,
            amount: abs($amount),
            currency: $currency,
            direction: $direction,
            variableSymbol: $variableSymbol,
            constantSymbol: $constantSymbol,
            specificSymbol: $specificSymbol,
            counterpartyName: $counterparty,
            reference: $reference,
            recipientIban: BankAccountIdentifier::normalize($main[2]),
            isExplicitCredit: $hasBankCreditHeader && $direction === BankTransactionDirection::Credit && $amount > 0,
            hasExplicitCurrency: true,
            e2eReference: $evidence['e2e'] ?? null,
            isVariableSymbolExplicit: ($evidence['vs'] ?? null) !== null,
            sepaCreditIdentity: $evidence['identity'] ?? null,
        );
    }

    /** @param array{amount: float, currency: string, date: Carbon, vs: ?string}|null $debit */
    protected function parseCompact(string $text, string $subject, ?array $debit = null): ?ParsedBankTransaction
    {
        $amount = $debit['amount'] ?? $this->matchAmount($text);
        if ($amount === null) {
            return null;
        }

        $bankPrefix = $this->bankAuthoredPrefix($text);
        $direction = $debit !== null ? BankTransactionDirection::Debit : app(BankTransactionDirectionGuesser::class)->fromAmountAndHints(
            $this->matchAmount($bankPrefix) ?? 0,
            $subject,
            $bankPrefix,
        );

        $vs = null;
        if (preg_match('/(?:VS|variabiln[ýy]\s*symbol)[:\s]*(\d+)/iu', $text, $m)) {
            $vs = BankSymbolNormalizer::variableSymbol($m[1]);
        }

        $bookedAt = $debit['date'] ?? Carbon::now();
        if ($debit === null && preg_match('/(\d{1,2}\.\d{1,2}\.\d{4})/u', $text, $dm)) {
            $bookedAt = Carbon::createFromFormat('d.m.Y', $dm[1]) ?: $bookedAt;
        }

        $protistrana = null;
        if (preg_match('/protistrana[:\s]+(.+?)(?:\n|$)/iu', $text, $pm)) {
            $protistrana = $this->cleanProtistrana(trim($pm[1]));
        }

        $popis = $this->matchField($text, 'Popis transakcie');
        $referencia = $this->matchField($text, 'Referencia platitela');
        $informacia = $this->matchField($text, 'Informacia pre prijemcu');

        [$variableSymbol, $constantSymbol, $specificSymbol, $referenciaName] = $this->parseReferenciaPlatitela($referencia);
        if ($debit !== null) {
            $variableSymbol = $debit['vs'];
        } else {
            $variableSymbol ??= $vs ?? $this->inferVariableSymbol($text, $informacia, $referencia);
        }

        $counterparty = $this->resolveCounterpartyName(
            $protistrana,
            $referenciaName,
            $popis,
            $informacia,
            $subject,
            $text,
        );

        $reference = $this->resolveReference($informacia, $popis, $referencia, $subject, $text);

        $currency = $debit['currency'] ?? $this->matchCurrency($text);

        return new ParsedBankTransaction(
            bookedAt: $bookedAt,
            amount: abs($amount),
            currency: $currency ?? 'EUR',
            direction: $direction,
            variableSymbol: $variableSymbol,
            constantSymbol: $constantSymbol,
            specificSymbol: $specificSymbol,
            counterpartyName: $counterparty,
            reference: $reference,
            recipientIban: $this->explicitRecipientIban($text),
            // Compact imports use permissive heuristics, including payer
            // remittance. No bounded bank-authored credit format is proven.
            isExplicitCredit: false,
            hasExplicitCurrency: $currency !== null,
        );
    }

    protected function explicitRecipientIban(string $text): ?string
    {
        preg_match_all(
            '/^(?:IBAN\s+pr[ií]jemcu|[uú][cč]et\s+pr[ií]jemcu|V[aá][sš]\s+[uú][cč]et)\s*:\s*([A-Z]{2}[0-9]{2}[A-Z0-9 \t]+?)[ \t]*[.;]?$/imu',
            $text,
            $matches,
        );
        $accounts = array_values(array_unique(array_filter(array_map(
            BankAccountIdentifier::normalize(...),
            $matches[1],
        ))));

        return count($accounts) === 1 ? $accounts[0] : null;
    }

    protected function matchField(string $text, string $label): ?string
    {
        $pattern = '/'.preg_quote($label, '/').':\s*(.+?)(?:\n|$)/iu';
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $value = trim($m[1]);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    protected function parseReferenciaPlatitela(?string $referencia): array
    {
        if ($referencia === null || $referencia === '') {
            return [null, null, null, null];
        }

        if (preg_match('/^\/VS([^\/]*)\/SS([^\/]*)\/KS(\S*)/iu', $referencia, $m)) {
            $vs = trim($m[1]) !== '' ? BankSymbolNormalizer::variableSymbol($m[1]) : null;
            $ss = trim($m[2]) !== '' ? BankSymbolNormalizer::constantSymbol($m[2]) : null;
            $ks = trim($m[3]) !== '' ? BankSymbolNormalizer::specificSymbol($m[3]) : null;

            return [$vs, $ss, $ks, null];
        }

        if (preg_match('/^\d{1,10}$/', trim($referencia))) {
            return [BankSymbolNormalizer::variableSymbol($referencia), null, null, null];
        }

        if (! str_contains($referencia, '/VS')) {
            return [null, null, null, $referencia];
        }

        return [null, null, null, null];
    }

    protected function inferVariableSymbol(?string $text, ?string $informacia, ?string $referencia): ?string
    {
        if ($text !== null && preg_match('/\bvs([0-9]{1,10})\b/iu', $text, $m)) {
            return BankSymbolNormalizer::variableSymbol($m[1]);
        }

        if ($informacia !== null && preg_match('/\b([0-9]{1,10})\b/u', $informacia, $m)) {
            return BankSymbolNormalizer::variableSymbol($m[1]);
        }

        if ($referencia !== null && preg_match('/\b([0-9]{1,10})\b/u', $referencia, $m)) {
            return BankSymbolNormalizer::variableSymbol($m[1]);
        }

        return null;
    }

    protected function resolveCounterpartyName(
        ?string $protistrana,
        ?string $referenciaName,
        ?string $popis,
        ?string $informacia,
        string $subject,
        string $text,
    ): ?string {
        if ($protistrana !== null && ! $this->isGenericNotificationText($protistrana)) {
            return $this->cleanProtistrana($protistrana);
        }

        if ($referenciaName !== null && ! $this->looksLikeSepaReference($referenciaName)) {
            return $referenciaName;
        }

        if ($popis !== null) {
            $fromPopis = $this->counterpartyFromPopis($popis);
            if ($fromPopis !== null) {
                return $fromPopis;
            }
        }

        if ($informacia !== null && $this->isHumanReadableMessage($informacia)) {
            return $informacia;
        }

        foreach ([$subject, $text] as $source) {
            $label = $this->notificationLabel($source);
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    protected function resolveReference(
        ?string $informacia,
        ?string $popis,
        ?string $referencia,
        string $subject,
        string $text,
    ): ?string {
        if ($informacia !== null && $this->isHumanReadableMessage($informacia)) {
            return $informacia;
        }

        if ($popis !== null && $referencia !== null) {
            return trim($popis.' · '.$referencia);
        }

        if ($popis !== null) {
            return $popis;
        }

        if ($referencia !== null) {
            return $referencia;
        }

        $subject = trim($subject);
        if ($subject !== '' && ! $this->isGenericNotificationText($subject)) {
            return $this->stripNotificationId($subject);
        }

        if (preg_match('/((?:debet|obrat|kredit|stav)[^.]{0,120})/iu', $text, $m)) {
            return $this->stripNotificationId(trim($m[1]));
        }

        return null;
    }

    protected function counterpartyFromPopis(string $popis): ?string
    {
        $popis = trim($popis);
        if ($popis === '') {
            return null;
        }

        if (preg_match('/^CCINT\s+(.+)$/iu', $popis, $m)) {
            return trim($m[1]);
        }

        return $popis;
    }

    protected function notificationLabel(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/EUR\s+N[AÁ]KUP\s+POS/iu', $text)) {
            return 'Platba kartou (POS)';
        }

        if (preg_match('/POS\s+n[aá]kup/iu', $text)) {
            return 'Platba kartou (POS)';
        }

        if (preg_match('/transak[cč]n[aá]\s+d[aá]n/iu', $text)) {
            return 'Transakčná daň';
        }

        if (preg_match('/^(debet|kredit|obrat|stav)\s+na\s+ucte/iu', $text, $m)) {
            return match (strtolower($m[1])) {
                'debet' => 'Bankový výdaj',
                'kredit' => 'Bankový príjem',
                'obrat' => 'Obrat na účte',
                'stav' => 'Stav na účte',
                default => null,
            };
        }

        return null;
    }

    protected function isGenericNotificationText(string $text): bool
    {
        return $this->notificationLabel($text) !== null
            || preg_match('/^(debet|kredit|obrat|stav)\s+na\s+ucte/iu', trim($text)) === 1;
    }

    protected function looksLikeSepaReference(string $text): bool
    {
        return str_contains($text, '(CdtrRefInf)')
            || str_contains($text, '(/Ref)')
            || preg_match('/^\d{1,10}$/', trim($text)) === 1;
    }

    protected function isHumanReadableMessage(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || $this->looksLikeSepaReference($text)) {
            return false;
        }

        return preg_match('/[A-Za-zÀ-ž]{2,}/u', $text) === 1;
    }

    protected function stripNotificationId(string $text): string
    {
        return trim(preg_replace('/\s*\(ID=[^)]+\)\s*/iu', '', $text) ?? $text);
    }

    protected function parseClassicDateTime(string $raw): ?Carbon
    {
        $normalized = preg_replace('/\s*\.\s*/', '.', trim($raw)) ?? trim($raw);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        foreach (['d.m.Y H:i', 'j.n.Y G:i'] as $format) {
            $parsed = Carbon::createFromFormat($format, $normalized);
            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        return null;
    }

    protected function cleanProtistrana(string $value): string
    {
        return trim(preg_replace('/\s+\d{1,2}\.\d{1,2}\.\d{4}\.?\s*$/u', '', $value) ?? $value);
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

    /** @return array{amount: float, currency: string, date: Carbon, vs: ?string}|null */
    private function qualifiedDebit(string $text, bool $classic): ?array
    {
        $bankPrefix = $this->bankAuthoredPrefix($text);
        if ($classic) {
            if (! preg_match('/\A(?:V[aá][zž]en[yý]\s+klient,\s*)?(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})[ \t]+(\d{1,2}):(\d{2})\s+bol zostatok Vasho uctu\s+\S+\s+znizeny\s+o\s+([0-9][0-9 ,.]*?)\s+([A-Z]{3})\.?[ \t]*(?:\n|$)/iu', $bankPrefix, $fields)) {
                return null;
            }
            $date = $this->debitDate($fields[1], $fields[2], $fields[3], $fields[4], $fields[5]);
            $amount = $this->debitAmount($fields[6]);
            $currency = strtoupper($fields[7]);
        } else {
            if (preg_match_all('/\b(?:suma|amount|obrat)[:\s]*(-?[0-9][0-9 ,.]*?)\s+([A-Z]{3})(?=[\s.]|$)/iu', $bankPrefix, $amountFields, PREG_SET_ORDER) !== 1
                || preg_match_all('/(?<![\d.])(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?(?!\d)/u', $bankPrefix, $dates, PREG_SET_ORDER) !== 1) {
                return null;
            }
            $date = $this->debitDate($dates[0][1], $dates[0][2], $dates[0][3], $dates[0][4] ?? '0', $dates[0][5] ?? '0', $dates[0][6] ?? '0');
            $amount = $this->debitAmount($amountFields[0][1]);
            $currency = strtoupper($amountFields[0][2]);
        }
        $symbol = $this->debitSymbol($text);
        if ($date === null || $amount === null || ! $symbol['valid']) {
            return null;
        }

        return ['amount' => $amount, 'currency' => $currency, 'date' => $date, 'vs' => $symbol['value']];
    }

    private function bankAuthoredPrefix(string $text): string
    {
        if (preg_match('/\b(?:Inform[aá]cia\s+pre\s+pr[ií]jemcu|Referencia\s+platite[lľ]a|Protistrana|Popis\s+transakcie|[UÚuú][cč]et\s+protistrany)\s*:/iu', $text, $field, PREG_OFFSET_CAPTURE)) {
            return substr($text, 0, $field[0][1]);
        }

        return $text;
    }

    private function debitDate(string $day, string $month, string $year, string $hour, string $minute, string $second = '0'): ?Carbon
    {
        if (! checkdate((int) $month, (int) $day, (int) $year)
            || (int) $hour > 23 || (int) $minute > 59 || (int) $second > 59) {
            return null;
        }

        return Carbon::create((int) $year, (int) $month, (int) $day, (int) $hour, (int) $minute, (int) $second);
    }

    private function debitAmount(string $raw): ?float
    {
        if (! preg_match('/\A-?(?:\d+|\d{1,3}(?: \d{3})+)(?:[,.]\d{1,2})?\z/', trim($raw))) {
            return null;
        }
        $canonical = str_replace([' ', ','], ['', '.'], trim($raw));
        $amount = (float) $canonical;

        return is_finite($amount) && abs($amount) > 0 && abs($amount) <= 999999999999.99 ? $amount : null;
    }

    /** @return array{valid: bool, value: ?string} */
    private function debitSymbol(string $text): array
    {
        preg_match_all('/\/VS([^\/\r\n]*)/iu', $text, $structured);
        preg_match_all('/(?<![\w\/])(?:VS|variabiln[ýy]\s*symbol)(?:[ \t]*:[ \t]*|[ \t]+)([^\s;]*)/iu', $text, $labelled, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $rawSymbols = $structured[1];
        foreach ($labelled as $field) {
            $value = $field[1][0];
            $tail = substr($text, $field[1][1] + strlen($value));
            $endsBeforeDate = str_ends_with($value, '.')
                && preg_match('/\A\h+\d{1,2}\.\h*\d{1,2}\.\h*\d{4}(?:[ T]+\d{1,2}:\d{2}(?::\d{2})?)?(?=\z|[\s.,;])/u', $tail);
            if (preg_match('/\A\h+\d/u', $tail) && ! $endsBeforeDate) {
                return ['valid' => false, 'value' => null];
            }
            $rawSymbols[] = str_ends_with($value, '.') ? substr($value, 0, -1) : $value;
        }
        $reference = $this->matchField($text, 'Referencia platitela');
        if ($reference !== null && preg_match('/\A\d{1,10}\z/', $reference)) {
            $rawSymbols[] = $reference;
        }
        $symbols = [];
        foreach ($rawSymbols as $raw) {
            if (trim($raw) === '') {
                continue;
            }
            $symbol = trim($raw);
            if (! preg_match('/\A\d{1,10}\z/', $symbol)) {
                return ['valid' => false, 'value' => null];
            }
            $symbols[] = $symbol;
        }
        $symbols = array_values(array_unique($symbols));

        return ['valid' => count($symbols) <= 1, 'value' => $symbols[0] ?? null];
    }
}
