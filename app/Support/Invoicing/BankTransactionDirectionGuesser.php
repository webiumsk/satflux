<?php

namespace App\Support\Invoicing;

use App\Enums\BankTransactionDirection;
use App\Models\BankTransaction;

final class BankTransactionDirectionGuesser
{
    /**
     * @param  list<string|null>  $hints
     */
    public function fromAmountAndHints(float $amount, ?string ...$hints): BankTransactionDirection
    {
        foreach ($hints as $hint) {
            if ($hint === null || trim($hint) === '') {
                continue;
            }
            $norm = mb_strtolower(trim($hint));
            if (in_array($norm, ['in', 'incoming'], true)) {
                return BankTransactionDirection::Credit;
            }
            if (in_array($norm, ['out', 'outgoing'], true)) {
                return BankTransactionDirection::Debit;
            }
        }

        $haystack = mb_strtolower(trim(implode(' ', array_filter($hints, fn (?string $h) => $h !== null && trim($h) !== ''))));

        if ($haystack !== '' && $this->containsDebitHint($haystack)) {
            return BankTransactionDirection::Debit;
        }

        if ($haystack !== '' && $this->containsCreditHint($haystack)) {
            return BankTransactionDirection::Credit;
        }

        return $amount < 0
            ? BankTransactionDirection::Debit
            : BankTransactionDirection::Credit;
    }

    public function inferFromTransaction(BankTransaction $transaction): BankTransactionDirection
    {
        return $this->fromAmountAndHints(
            (float) $transaction->amount,
            $transaction->reference,
            $transaction->counterparty_name,
        );
    }

    protected function containsDebitHint(string $haystack): bool
    {
        foreach ($this->debitHints() as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function containsCreditHint(string $haystack): bool
    {
        if (str_contains($haystack, 'obrat na') && ! str_contains($haystack, 'debet')) {
            return true;
        }

        foreach ($this->creditHints() as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function debitHints(): array
    {
        $hints = config('invoicing.bank_debit_hints');

        return is_array($hints) && $hints !== [] ? array_values($hints) : $this->defaultDebitHints();
    }

    /**
     * @return list<string>
     */
    protected function creditHints(): array
    {
        $hints = config('invoicing.bank_credit_hints');

        return is_array($hints) && $hints !== [] ? array_values($hints) : $this->defaultCreditHints();
    }

    /**
     * @return list<string>
     */
    protected function defaultDebitHints(): array
    {
        return [
            'debet na',
            'debetna',
            'debet ',
            'debet.',
            'debit',
            'dbit',
            'odchod',
            'odch.',
            'odchodz',
            'výdaj',
            'vydaj',
            'nákup pos',
            'nakup pos',
            'eur nákup',
            'eur nakup',
            'pos nákup',
            'pos nakup',
            'transakčná daň',
            'transakcna dan',
            'poplatok',
            'výber',
            'vyber',
            'platba kartou',
            'platba prevodom',
            'smerom von',
        ];
    }

    /**
     * @return list<string>
     */
    protected function defaultCreditHints(): array
    {
        return [
            'kredit na',
            'kredit ',
            'credit',
            'príjem',
            'prijem',
            'prijatie',
            'vklad',
            'pripis',
            'pripísan',
            'pripisan',
        ];
    }
}
