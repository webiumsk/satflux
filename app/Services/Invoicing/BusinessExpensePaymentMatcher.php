<?php

namespace App\Services\Invoicing;

use App\Enums\BankImportSource;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessExpenseStatus;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessExpense;
use App\Support\Invoicing\BankSymbolNormalizer;
use Illuminate\Database\Eloquent\Builder;

/** Company bank-email bookkeeping; never a Store payment-origin attestation. */
class BusinessExpensePaymentMatcher
{
    /**
     * Atomically link one exact eligible expense to a recorded bank-email debit.
     * Locked rechecks preserve uniqueness; the expense, movement and audit share one transaction.
     */
    public function tryAutoMatch(BankTransaction $transaction, ?int $userId = null): bool
    {
        $connection = $transaction->getConnection();
        $connectionName = $connection->getName();

        $linked = $connection->transaction(function () use ($transaction, $userId, $connectionName): ?BankTransaction {
            $locked = BankTransaction::on($connectionName)
                ->whereKey($transaction->id)
                ->where('company_id', $transaction->company_id)
                ->lockForUpdate()->first();
            if ($locked === null || ! $this->eligibleTransaction($locked, $connectionName)) {
                return null;
            }

            $amount = $this->positiveCents($locked->getRawOriginal('amount'));
            $currency = $this->currency($locked->getRawOriginal('currency'));
            $paidAt = $this->bookingDate($locked->getRawOriginal('booked_at'));
            if ($amount === null || $currency === null || $paidAt === null) {
                return null;
            }
            $paddedAmount = str_pad($amount, 3, '0', STR_PAD_LEFT);
            $decimalAmount = substr($paddedAmount, 0, -2).'.'.substr($paddedAmount, -2);

            // A consistent order serializes competing email matchers. Refresh
            // candidate state after waiting for the locks before deciding uniqueness.
            $this->candidateQuery($locked, $connectionName, $decimalAmount, $currency)->lockForUpdate()->get();
            $candidates = $this->candidateQuery($locked, $connectionName, $decimalAmount, $currency)->lockForUpdate()->get()
                ->filter(fn (BusinessExpense $expense): bool => $this->positiveCents($expense->getRawOriginal('total')) === $amount
                    && $this->currency($expense->getRawOriginal('currency')) === $currency
                    && $this->matchesHints($locked, $expense)
                    && ! BankTransaction::on($connectionName)->where('business_expense_id', $expense->id)->exists());

            if ($candidates->count() !== 1 || ! $this->eligibleTransaction($locked, $connectionName)) {
                return null;
            }
            $expense = $candidates->first();

            $expense->update(['status' => BusinessExpenseStatus::Paid, 'paid_at' => $paidAt]);
            $locked->update([
                'business_expense_id' => $expense->id,
                'match_status' => BankTransactionMatchStatus::Matched,
            ]);
            // Explicit connection keeps the bounded audit in the same rollback
            // boundary as both bookkeeping updates, even on a named connection.
            AuditLog::on($connectionName)->create([
                'user_id' => $userId,
                'action' => 'bank_transaction.expense_matched',
                'target_type' => 'bank_transaction',
                'target_id' => $locked->id,
                'metadata' => [
                    'company_id' => $locked->company_id,
                    'business_expense_id' => $expense->id,
                    'match_type' => 'auto',
                ],
            ]);

            return $locked;
        });

        if ($linked === null) {
            return false;
        }
        $transaction->setRawAttributes($linked->getAttributes(), true);
        $transaction->unsetRelation('expense');
        $transaction->unsetRelation('match');

        return true;
    }

    /**
     * Each call reads the current invoice-link state from the database.
     *
     * @phpstan-impure
     */
    private function eligibleTransaction(BankTransaction $transaction, string $connectionName): bool
    {
        return $transaction->source === BankImportSource::Email->value
            && $transaction->getRawOriginal('direction') === BankTransactionDirection::Debit->value
            && $transaction->getRawOriginal('match_status') === BankTransactionMatchStatus::Unmatched->value
            && $transaction->business_expense_id === null
            && ! $transaction->isAccountBalanceSnapshot()
            && ! BankTransactionMatch::on($connectionName)->where('bank_transaction_id', $transaction->id)->exists();
    }

    /** @return Builder<BusinessExpense> */
    private function candidateQuery(BankTransaction $transaction, string $connectionName, string $amount, string $currency): Builder
    {
        return BusinessExpense::on($connectionName)
            ->where('company_id', $transaction->company_id)
            ->where('status', BusinessExpenseStatus::Recorded)
            ->whereNull('paid_at')->whereNull('cancelled_at')
            ->where('total', $amount)
            ->whereRaw('UPPER(TRIM(currency)) = ?', [$currency])
            ->orderBy('id');
    }

    /** Prefer a valid bank VS; without one, require the full supplier document reference to match. */
    private function matchesHints(BankTransaction $transaction, BusinessExpense $expense): bool
    {
        $bankVs = trim((string) $transaction->variable_symbol);
        $expenseVs = trim((string) $expense->variable_symbol);
        if ($expenseVs !== '' && BankSymbolNormalizer::variableSymbol($expenseVs) === null) {
            return false;
        }
        if ($bankVs !== '') {
            $symbol = BankSymbolNormalizer::variableSymbol($bankVs);

            return $symbol !== null && $symbol === BankSymbolNormalizer::variableSymbol(
                $expenseVs !== '' ? $expenseVs : $expense->external_number,
            );
        }
        $reference = trim((string) $transaction->reference);

        return $reference !== '' && $reference === trim((string) $expense->external_number);
    }

    /** Return canonical positive cent digits only when the raw database amount needs no rounding. */
    private function positiveCents(mixed $value): ?string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            // SQLite returns decimal storage as a float. Round-trip the exact
            // stored value, rejecting any value that needs rounding to cents.
            $formatted = number_format($value, 2, '.', '');
            if ((float) $formatted !== $value) {
                return null;
            }
            $value = $formatted;
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        if (! preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', trim((string) $value), $parts)) {
            return null;
        }
        $cents = ltrim($parts[1].str_pad($parts[2] ?? '', 2, '0'), '0');

        return $cents !== '' ? $cents : null;
    }

    /** Normalize a stored currency to three ASCII letters, rejecting malformed or absent codes. */
    private function currency(mixed $value): ?string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/\A[A-Z]{3}\z/', $currency) ? $currency : null;
    }

    /** Validate a stored date or timestamp and retain its original bank calendar date without timezone conversion. */
    private function bookingDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match(
            '/\A(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?(?:Z|([+-])(\d{2}):(\d{2}))?)?\z/',
            $value, $parts,
        )) {
            return null;
        }
        if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || (isset($parts[4]) && ((int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59))
            || (isset($parts[8]) && ((int) $parts[8] > 23 || (int) $parts[9] > 59))) {
            return null;
        }

        return substr($value, 0, 10);
    }
}
