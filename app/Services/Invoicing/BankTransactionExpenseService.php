<?php

namespace App\Services\Invoicing;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BusinessExpense;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankTransactionExpenseService
{
    public function __construct(
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromTransaction(
        Company $company,
        BankTransaction $transaction,
        array $data,
    ): BusinessExpense {
        if ($transaction->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'bank_transaction' => ['Transaction does not belong to this company.'],
            ]);
        }

        if ($transaction->resolvedDirection() !== BankTransactionDirection::Debit) {
            throw ValidationException::withMessages([
                'direction' => ['Expenses can only be created from outgoing bank movements.'],
            ]);
        }

        if ($transaction->business_expense_id !== null) {
            throw ValidationException::withMessages([
                'bank_transaction' => ['This movement is already linked to an expense.'],
            ]);
        }

        if ($transaction->match_status === BankTransactionMatchStatus::Matched && $transaction->match) {
            throw ValidationException::withMessages([
                'bank_transaction' => ['This movement is already matched to an invoice.'],
            ]);
        }

        return DB::transaction(function () use ($company, $transaction, $data) {
            $issueDate = $data['issue_date'] ?? $transaction->booked_at->toDateString();
            $supplier = trim((string) ($data['supplier'] ?? $transaction->counterparty_name ?? ''));
            $category = trim((string) ($data['category'] ?? ''));
            $title = trim((string) ($data['title'] ?? ''));

            if ($title === '') {
                $title = $this->defaultTitle($supplier, $category, $transaction);
            }

            $internalNote = trim((string) ($data['internal_note'] ?? ''));
            if ($internalNote === '') {
                $internalNote = $this->defaultInternalNote($transaction, $supplier, $category);
            }

            $expense = $this->expenseService->create($company, [
                'title' => $title,
                'variable_symbol' => $data['variable_symbol'] ?? $transaction->variable_symbol,
                'constant_symbol' => $data['constant_symbol'] ?? $transaction->constant_symbol,
                'specific_symbol' => $data['specific_symbol'] ?? $transaction->specific_symbol,
                'issue_date' => $issueDate,
                'delivery_date' => $data['delivery_date'] ?? $issueDate,
                'due_date' => $data['due_date'] ?? $issueDate,
                'total' => $data['total'] ?? $transaction->amount,
                'currency' => $data['currency'] ?? $transaction->currency,
                'internal_note' => $internalNote,
            ], (bool) ($data['mark_paid'] ?? true));

            $transaction->update([
                'business_expense_id' => $expense->id,
                'match_status' => BankTransactionMatchStatus::Matched,
            ]);

            AuditLog::log('bank_transaction.expense_created', 'bank_transaction', $transaction->id, [
                'business_expense_id' => $expense->id,
                'company_id' => $company->id,
            ]);

            return $expense->fresh();
        });
    }

    protected function defaultTitle(string $supplier, string $category, BankTransaction $transaction): string
    {
        if ($supplier !== '' && $category !== '') {
            return $supplier.' - '.$category;
        }

        if ($supplier !== '') {
            return $supplier;
        }

        if ($category !== '') {
            return $category;
        }

        $reference = trim((string) ($transaction->reference ?? ''));

        return $reference !== '' ? $reference : __('invoicing.bank_expense_fallback');
    }

    protected function defaultInternalNote(
        BankTransaction $transaction,
        string $supplier,
        string $category,
    ): string {
        $lines = [];

        if ($supplier !== '') {
            $lines[] = __('invoicing.bank_expense_note_supplier').': '.$supplier;
        }
        if ($category !== '') {
            $lines[] = __('invoicing.bank_expense_note_category').': '.$category;
        }

        $reference = trim((string) ($transaction->reference ?? ''));
        if ($reference !== '') {
            $lines[] = $reference;
        }

        return implode("\n", $lines);
    }
}
