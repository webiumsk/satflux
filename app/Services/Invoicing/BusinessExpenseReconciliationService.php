<?php

namespace App\Services\Invoicing;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessExpenseStatus;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Support\Invoicing\BankSymbolNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Explicit company bookkeeping; suggestions never authorize Store payment settlement. */
class BusinessExpenseReconciliationService
{
    /** @return list<array{expense: array<string, mixed>, reason: string}> */
    public function suggestions(Company $company, BankTransaction $transaction): array
    {
        $this->assertServerCompany($company);
        if (! $this->eligibleMovement($transaction)) {
            return [];
        }

        $amount = $this->positiveCents($transaction->getRawOriginal('amount'));
        $currency = $this->currency($transaction->getRawOriginal('currency'));
        if ($amount === null || $currency === null) {
            return [];
        }

        $suggestions = [];
        foreach ($this->candidateQuery($transaction, $amount, $currency)->cursor() as $expense) {
            $reason = $this->matchReason($transaction, $expense);
            if ($reason !== null) {
                $suggestions[] = ['expense' => $this->expensePayload($expense), 'reason' => $reason];
                if (count($suggestions) === 20) {
                    break;
                }
            }
        }

        return $suggestions;
    }

    /** Confirm one still-valid suggestion after reloading both records under locks. */
    public function match(Company $company, BankTransaction $transaction, string $expenseId, ?int $userId): BusinessExpense
    {
        $this->assertServerCompany($company);
        $connection = $transaction->getConnection();
        $connectionName = $connection->getName();

        return $connection->transaction(function () use ($company, $transaction, $expenseId, $userId, $connectionName): BusinessExpense {
            $locked = BankTransaction::on($connectionName)->whereKey($transaction->id)
                ->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            // Match createFromTransaction's movement -> company -> expense lock order.
            $currentCompany = Company::on($connectionName)->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $this->assertServerCompany($currentCompany);
            $expense = BusinessExpense::on($connectionName)->whereKey($expenseId)
                ->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            $amount = $this->positiveCents($locked->getRawOriginal('amount'));
            $currency = $this->currency($locked->getRawOriginal('currency'));

            if (! $this->validMovementEvidence($locked)
                || $amount === null || $currency === null
                || $this->positiveCents($expense->getRawOriginal('total')) !== $amount
                || $this->currency($expense->getRawOriginal('currency')) !== $currency
                || $this->matchReason($locked, $expense) === null
                || $expense->getRawOriginal('status') === BusinessExpenseStatus::Cancelled->value
                || $expense->getRawOriginal('cancelled_at') !== null
                || BankTransaction::on($connectionName)->where('business_expense_id', $expense->id)
                    ->whereKeyNot($locked->id)->exists()
                || BankTransactionMatch::on($connectionName)->where('bank_transaction_id', $locked->id)->exists()) {
                $this->invalidMatch();
            }

            // A retried confirmation is a no-op; it must not rewrite paid dates or duplicate history.
            if ($locked->business_expense_id === $expense->id
                && $locked->getRawOriginal('match_status') === BankTransactionMatchStatus::Matched->value
                && $expense->getRawOriginal('status') === BusinessExpenseStatus::Paid->value
                && $expense->getRawOriginal('paid_at') !== null) {
                return $expense;
            }

            if (! $this->eligibleMovement($locked)
                || $expense->getRawOriginal('status') !== BusinessExpenseStatus::Recorded->value
                || $expense->getRawOriginal('paid_at') !== null
                || BankTransaction::on($connectionName)->where('business_expense_id', $expense->id)->exists()) {
                $this->invalidMatch();
            }

            $expense->update([
                'status' => BusinessExpenseStatus::Paid,
                'paid_at' => substr((string) $locked->getRawOriginal('booked_at'), 0, 10),
            ]);
            $locked->update([
                'business_expense_id' => $expense->id,
                'match_status' => BankTransactionMatchStatus::Matched,
            ]);
            AuditLog::on($connectionName)->create([
                'user_id' => $userId,
                'action' => 'bank_transaction.expense_matched',
                'target_type' => 'bank_transaction',
                'target_id' => $locked->id,
                'metadata' => [
                    'company_id' => $company->id,
                    'business_expense_id' => $expense->id,
                    'match_type' => 'manual',
                ],
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Advisory similarities, never supplier identity claims or a reason to reject saving.
     *
     * @param  array<string, mixed>  $draft
     * @return list<array{expense: array<string, mixed>, reason: string}>
     */
    public function duplicateSuggestions(Company $company, array $draft): array
    {
        $this->assertServerCompany($company);
        $excludeId = $draft['exclude_id'] ?? null;
        if ($excludeId !== null) {
            BusinessExpense::query()->where('company_id', $company->id)->whereKey($excludeId)->firstOrFail();
        }
        $amount = $this->positiveCents($draft['total']);
        $currency = $this->currency($draft['currency']);
        if ($amount === null || $currency === null) {
            return [];
        }
        $number = $this->normalizedText($draft['external_number'] ?? null);
        $title = $this->normalizedText($draft['title'] ?? null, true);
        $date = $draft['issue_date'] ?? null;
        if ($number === '' && ($title === '' || $date === null)) {
            return [];
        }

        $query = BusinessExpense::query()->where('company_id', $company->id)
            ->where('status', '!=', BusinessExpenseStatus::Cancelled)->whereNull('cancelled_at')
            ->where('total', $this->decimalAmount($amount))
            ->whereRaw('UPPER(TRIM(currency)) = ?', [$currency])->orderBy('id');
        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }
        $suggestions = [];
        foreach ($query->cursor() as $expense) {
            $reason = null;
            if ($number !== '' && $number === $this->normalizedText($expense->external_number)) {
                $reason = 'external_number';
            } elseif ($title !== '' && $date !== null
                && $title === $this->normalizedText($expense->title, true)
                && substr((string) $expense->getRawOriginal('issue_date'), 0, 10) === $date) {
                $reason = 'title_date_amount';
            }
            if ($reason !== null) {
                $payload = $this->expensePayload($expense);
                unset($payload['variable_symbol']);
                $suggestions[] = ['expense' => $payload, 'reason' => $reason];
                if (count($suggestions) === 20) {
                    break;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Index only the bounded file's keys, streaming company candidates once instead of querying for every row.
     *
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array{row: int, expense: array<string, mixed>, reason: string, in_file: bool}>
     */
    public function importDuplicateSuggestions(Company $company, array $drafts): array
    {
        $this->assertServerCompany($company);
        $needed = [];
        foreach ($drafts as $draft) {
            foreach ($this->duplicateKeys($draft) as $key) {
                $needed[$key] = true;
            }
        }
        if ($needed === []) {
            return [];
        }

        $index = [];
        $query = BusinessExpense::query()->where('company_id', $company->id)
            ->where('status', '!=', BusinessExpenseStatus::Cancelled)->whereNull('cancelled_at')
            ->orderBy('internal_number')->orderBy('id');
        foreach ($query->cursor() as $expense) {
            $payload = $this->expensePayload($expense);
            unset($payload['variable_symbol']);
            foreach ($this->duplicateKeys($payload) as $key) {
                if (isset($needed[$key]) && ! isset($index[$key])) {
                    $index[$key] = ['expense' => $payload, 'in_file' => false];
                }
            }
        }

        $warnings = [];
        foreach ($drafts as $draft) {
            $keys = $this->duplicateKeys($draft);
            foreach ($keys as $reason => $key) {
                if (isset($index[$key])) {
                    $warnings[] = array_merge($index[$key], ['row' => $draft['row'], 'reason' => $reason]);
                    break;
                }
            }
            if (count($warnings) === 20) {
                break;
            }
            $payload = [
                'id' => 'preview-row-'.$draft['row'],
                'internal_number' => (string) $draft['row'],
                'external_number' => $draft['external_number'] ?? null,
                'title' => $draft['title'] ?? null,
                'total' => $draft['total'],
                'currency' => $draft['currency'],
                'issue_date' => $draft['issue_date'],
                'status' => 'recorded',
            ];
            foreach ($keys as $key) {
                if (! isset($index[$key])) {
                    $index[$key] = ['expense' => $payload, 'in_file' => true];
                }
            }
        }

        return $warnings;
    }

    /** @param array<string, mixed> $draft
     * @return array<string, string>
     */
    private function duplicateKeys(array $draft): array
    {
        $amount = $this->positiveCents($draft['total'] ?? null);
        $currency = $this->currency($draft['currency'] ?? null);
        if ($amount === null || $currency === null) {
            return [];
        }
        $keys = [];
        $number = $this->normalizedText($draft['external_number'] ?? null);
        if ($number !== '') {
            $keys['external_number'] = json_encode(['number', $amount, $currency, $number], JSON_THROW_ON_ERROR);
        }
        $title = $this->normalizedText($draft['title'] ?? null, true);
        $date = $draft['issue_date'] ?? null;
        if ($title !== '' && is_string($date) && $date !== '') {
            $keys['title_date_amount'] = json_encode(['title', $amount, $currency, $title, $date], JSON_THROW_ON_ERROR);
        }

        return $keys;
    }

    /** @return array<string, mixed> */
    private function expensePayload(BusinessExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'internal_number' => $expense->internal_number,
            'external_number' => $expense->external_number,
            'title' => $expense->title,
            'total' => $expense->total,
            'currency' => $expense->currency,
            'issue_date' => substr((string) $expense->getRawOriginal('issue_date'), 0, 10),
            'variable_symbol' => $expense->variable_symbol,
            'status' => $expense->getRawOriginal('status'),
        ];
    }

    /** @return Builder<BusinessExpense> */
    private function candidateQuery(BankTransaction $transaction, string $amount, string $currency): Builder
    {
        $connectionName = $transaction->getConnectionName();

        return BusinessExpense::on($connectionName)
            ->where('company_id', $transaction->company_id)
            ->where('status', BusinessExpenseStatus::Recorded)->whereNull('paid_at')->whereNull('cancelled_at')
            ->where('total', $this->decimalAmount($amount))
            ->whereRaw('UPPER(TRIM(currency)) = ?', [$currency])
            ->whereNotIn('id', BankTransaction::on($connectionName)->whereNotNull('business_expense_id')->select('business_expense_id'))
            ->orderBy('id');
    }

    private function eligibleMovement(BankTransaction $transaction): bool
    {
        return $this->validMovementEvidence($transaction)
            && $transaction->getRawOriginal('match_status') === BankTransactionMatchStatus::Unmatched->value
            && $transaction->business_expense_id === null
            && ! BankTransactionMatch::on($transaction->getConnectionName())->where('bank_transaction_id', $transaction->id)->exists();
    }

    /** Recorded direction is evidence; free-text direction inference is not. */
    private function validMovementEvidence(BankTransaction $transaction): bool
    {
        $date = $transaction->getRawOriginal('booked_at');
        if ($transaction->getRawOriginal('direction') !== BankTransactionDirection::Debit->value
            || $transaction->isAccountBalanceSnapshot()
            || ! is_string($date)
            || ! preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?(?:Z|([+-])(\d{2}):(\d{2}))?)?\z/', $date, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (! isset($parts[4]) || ((int) $parts[4] <= 23 && (int) $parts[5] <= 59 && (int) $parts[6] <= 59))
            && (! isset($parts[8]) || ((int) $parts[8] <= 23 && (int) $parts[9] <= 59));
    }

    /** Match the existing bank-email matcher's strict VS precedence and complete-reference fallback. */
    private function matchReason(BankTransaction $transaction, BusinessExpense $expense): ?string
    {
        $bankVs = trim((string) $transaction->variable_symbol);
        $expenseVs = trim((string) $expense->variable_symbol);
        if ($expenseVs !== '' && BankSymbolNormalizer::variableSymbol($expenseVs) === null) {
            return null;
        }
        if ($bankVs !== '') {
            $symbol = BankSymbolNormalizer::variableSymbol($bankVs);

            return $symbol !== null && $symbol === BankSymbolNormalizer::variableSymbol(
                $expenseVs !== '' ? $expenseVs : $expense->external_number,
            ) ? 'variable_symbol' : null;
        }
        $reference = trim((string) $transaction->reference);

        return $reference !== '' && $reference === trim((string) $expense->external_number) ? 'payment_reference' : null;
    }

    /** Exact canonical positive cents; reject fractional cents instead of rounding payment evidence. */
    private function positiveCents(mixed $value): ?string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $formatted = number_format($value, 2, '.', '');
            if ((float) $formatted !== $value) {
                return null;
            }
            $value = $formatted;
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        if (! preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', trim((string) $value), $parts)) {
            return null;
        }
        $cents = ltrim($parts[1].str_pad($parts[2] ?? '', 2, '0'), '0');

        return $cents !== '' ? $cents : null;
    }

    private function decimalAmount(string $cents): string
    {
        $padded = str_pad($cents, 3, '0', STR_PAD_LEFT);

        return substr($padded, 0, -2).'.'.substr($padded, -2);
    }

    private function currency(mixed $value): ?string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/\A[A-Z]{3}\z/', $currency) ? $currency : null;
    }

    private function normalizedText(mixed $value, bool $collapseWhitespace = false): string
    {
        $trimmed = preg_replace('/\A[\s\x{FEFF}]+|[\s\x{FEFF}]+\z/u', '', (string) $value) ?? trim((string) $value);
        $text = mb_strtolower($trimmed);

        return $collapseWhitespace ? (preg_replace('/[\s\x{FEFF}]+/u', ' ', $text) ?? $text) : $text;
    }

    private function assertServerCompany(Company $company): void
    {
        if (! $company->usesServerInvoicing()) {
            throw ValidationException::withMessages(['company' => ['Use local expense reconciliation for this company.']]);
        }
    }

    private function invalidMatch(): never
    {
        throw ValidationException::withMessages(['expense_id' => ['The movement and expense are no longer eligible for this match.']]);
    }
}
