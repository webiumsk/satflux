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

        foreach (preg_split('/\r?\n/', $note) ?: [] as $line) {
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
}
