<?php

namespace App\Support\Invoicing;

/** Preserve payment uncertainty independently of editable supplier fields and notes. */
final class ExpensePaymentReview
{
    public static function required(?bool $stored, ?string $originalNote): bool
    {
        if ($stored !== null) {
            return $stored;
        }

        $note = $originalNote ?? '';
        // Historical extractors withheld accounts for financial ambiguity without
        // storing the reason. A contact account cannot recover that missing proof.
        if (preg_match('/Imported from ISDOC\.|Peppol \(SAPI-SK\)\./i', $note)) {
            return true;
        }

        return self::hasPaymentEvidence($note);
    }

    /** Current saved payment evidence vetoes this operation before submitted note edits can remove it. */
    public static function hasPaymentEvidence(?string $note): bool
    {
        foreach (preg_split('/\r?\n/', $note ?? '') ?: [] as $line) {
            if (str_starts_with(strtolower($line), 'payment date:')) {
                return true;
            }
            if (str_starts_with(strtolower($line), 'payment amount:')
                && ! preg_match('/^0+(?:[.,]0{1,2})?$/D', trim(substr($line, strlen('Payment amount:'))))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bind manual verification to the exact eligible payment instructions, never to an XML validity claim.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function confirmationTuple(array $fields): ?string
    {
        $name = $fields['supplier_name'] ?? null;
        $account = $fields['supplier_iban'] ?? null;
        $symbol = $fields['variable_symbol'] ?? null;
        if (! is_string($name) || ! is_string($account) || ! is_string($symbol)) {
            return null;
        }
        $name = self::trimText($name);
        $symbol = self::trimText($symbol);
        $iban = SupplierPaymentIban::normalize($account);
        if ($name === '' || preg_match('//u', $name) !== 1 || mb_strlen($name) > 70
            || preg_match('/[\p{Cc}\p{Cs}\p{Zl}\p{Zp}]/u', $name)
            || $iban === null || ! preg_match('/^[0-9]{1,10}$/D', $symbol)
            || ($fields['currency'] ?? null) !== 'EUR') {
            return null;
        }
        $total = $fields['total'] ?? null;
        if (is_float($total)) {
            if (! is_finite($total)) {
                return null;
            }
            $formatted = number_format($total, 2, '.', '');
            if ((float) $formatted !== $total) {
                return null;
            }
            $total = $formatted;
        }
        if ((! is_string($total) && ! is_int($total))
            || ! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', self::trimText((string) $total), $parts)) {
            return null;
        }
        $integer = ltrim($parts[1], '0') ?: '0';
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        if ($integer === '0' && $fraction === '00') {
            return null;
        }

        return json_encode([$name, $iban, $integer.'.'.$fraction, 'EUR', $symbol], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Match JavaScript String.trim rather than allowing locale-dependent whitespace differences. */
    private static function trimText(string $value): string
    {
        return preg_replace('/\A[ \x09-\x0D\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+|[ \x09-\x0D\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+\z/u', '', $value) ?? $value;
    }
}
