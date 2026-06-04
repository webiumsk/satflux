<?php

namespace App\Services\Invoicing;

use App\Enums\BankMatchType;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\BankSymbolNormalizer;
use Illuminate\Support\Collection;

class BusinessDocumentPaymentMatcher
{
    public function __construct(
        protected BusinessDocumentMarkPaidService $markPaidService,
    ) {}

    /**
     * @return array{matched: bool, match: ?BankTransactionMatch}
     */
    public function tryAutoMatch(BankTransaction $transaction, ?int $userId = null): array
    {
        if ($transaction->match_status === BankTransactionMatchStatus::Matched) {
            return ['matched' => true, 'match' => $transaction->match];
        }

        if ($transaction->direction !== BankTransactionDirection::Credit) {
            return ['matched' => false, 'match' => null];
        }

        if ($transaction->variable_symbol === null || $transaction->variable_symbol === '') {
            return ['matched' => false, 'match' => null];
        }

        $document = $this->findExactMatch($transaction->company, $transaction);
        if ($document === null) {
            return ['matched' => false, 'match' => null];
        }

        $match = $this->applyMatch(
            $transaction,
            $document,
            (float) $transaction->amount,
            BankMatchType::Auto,
            $userId,
        );

        return ['matched' => true, 'match' => $match];
    }

    /**
     * @return list<array{document: BusinessDocument, reason: string}>
     */
    public function suggestions(BankTransaction $transaction, int $limit = 10): array
    {
        if ($transaction->direction !== BankTransactionDirection::Credit) {
            return [];
        }

        $vs = $transaction->variable_symbol;
        $query = BusinessDocument::query()
            ->where('company_id', $transaction->company_id)
            ->where('status', BusinessDocumentStatus::Issued)
            ->whereIn('type', [
                BusinessDocumentType::Invoice,
                BusinessDocumentType::Proforma,
            ])
            ->orderByDesc('issue_date')
            ->limit(50);

        $tolerance = (float) config('bank_import.amount_tolerance', 0.01);
        $out = [];

        foreach ($query->get()->filter(fn ($doc) => $this->matchesVariableSymbol($doc, $vs)) as $document) {
            $due = $this->amountDue($document);
            $reason = 'variable_symbol';
            if (abs((float) $transaction->amount - $due) > $tolerance) {
                $reason = 'amount_mismatch';
            }
            if (strtoupper((string) $transaction->currency) !== strtoupper((string) $document->currency)) {
                $reason = 'currency_mismatch';
            }
            $out[] = ['document' => $document, 'reason' => $reason];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function manualMatch(
        BankTransaction $transaction,
        BusinessDocument $document,
        ?float $matchedAmount = null,
        ?int $userId = null,
    ): BankTransactionMatch {
        if ($transaction->company_id !== $document->company_id) {
            throw new \InvalidArgumentException('Transaction and document belong to different companies.');
        }

        if ($transaction->match_status === BankTransactionMatchStatus::Matched) {
            throw new \InvalidArgumentException('Transaction is already matched.');
        }

        return $this->applyMatch(
            $transaction,
            $document,
            $matchedAmount ?? (float) $transaction->amount,
            BankMatchType::Manual,
            $userId,
        );
    }

    public function ignore(BankTransaction $transaction, ?int $userId = null): BankTransaction
    {
        $transaction->update(['match_status' => BankTransactionMatchStatus::Ignored]);

        AuditLog::log('bank_transaction.ignored', 'bank_transaction', $transaction->id, [
            'company_id' => $transaction->company_id,
        ], $userId);

        return $transaction->fresh();
    }

    public function unmatch(BankTransaction $transaction, ?int $userId = null): BankTransaction
    {
        $match = $transaction->match;
        if ($match) {
            $document = $match->document;
            $this->markPaidService->unmarkPaid($document, $userId);
            $match->delete();
        }

        $transaction->update(['match_status' => BankTransactionMatchStatus::Unmatched]);

        AuditLog::log('bank_transaction.unmatched', 'bank_transaction', $transaction->id, [
            'company_id' => $transaction->company_id,
        ], $userId);

        return $transaction->fresh();
    }

    /**
     * @param  Collection<int, BankTransaction>  $transactions
     * @return array{auto_matched: int}
     */
    public function autoMatchBatch(Collection $transactions, ?int $userId = null): array
    {
        $count = 0;
        foreach ($transactions as $transaction) {
            $result = $this->tryAutoMatch($transaction, $userId);
            if ($result['matched']) {
                $count++;
            }
        }

        return ['auto_matched' => $count];
    }

    protected function findExactMatch(Company $company, BankTransaction $transaction): ?BusinessDocument
    {
        $vs = BankSymbolNormalizer::variableSymbol($transaction->variable_symbol);
        if ($vs === null) {
            return null;
        }

        $candidates = BusinessDocument::query()
            ->where('company_id', $company->id)
            ->where('status', BusinessDocumentStatus::Issued)
            ->whereIn('type', [BusinessDocumentType::Invoice, BusinessDocumentType::Proforma])
            ->get()
            ->filter(fn ($doc) => $this->matchesVariableSymbol($doc, $vs));

        if ($candidates->isEmpty()) {
            return null;
        }

        $tolerance = (float) config('bank_import.amount_tolerance', 0.01);
        $currency = strtoupper((string) $transaction->currency);
        $amount = (float) $transaction->amount;

        $exact = $candidates->filter(function (BusinessDocument $doc) use ($amount, $currency, $tolerance) {
            return strtoupper((string) $doc->currency) === $currency
                && abs($this->amountDue($doc) - $amount) <= $tolerance;
        });

        if ($exact->count() === 1) {
            return $exact->first();
        }

        return null;
    }

    protected function amountDue(BusinessDocument $document): float
    {
        $paid = (float) ($document->amount_paid ?? 0);

        return max(0, (float) $document->total - $paid);
    }

    protected function matchesVariableSymbol(BusinessDocument $document, ?string $vs): bool
    {
        if ($vs === null || $vs === '') {
            return true;
        }

        if ((string) $document->variable_symbol === $vs) {
            return true;
        }

        $fromNumber = preg_replace('/\D/', '', (string) ($document->number ?? ''));

        return $fromNumber !== '' && $fromNumber === $vs;
    }

    protected function applyMatch(
        BankTransaction $transaction,
        BusinessDocument $document,
        float $matchedAmount,
        BankMatchType $type,
        ?int $userId,
    ): BankTransactionMatch {
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $transaction->id,
            'business_document_id' => $document->id,
            'matched_amount' => round($matchedAmount, 2),
            'match_type' => $type,
            'matched_by_user_id' => $userId,
            'matched_at' => now(),
        ]);

        $transaction->update(['match_status' => BankTransactionMatchStatus::Matched]);

        $this->markPaidService->markPaid(
            $document,
            $matchedAmount,
            $transaction,
            $type === BankMatchType::Auto ? 'bank_auto' : 'bank_manual',
            $userId,
        );

        AuditLog::log('bank_transaction.matched', 'bank_transaction', $transaction->id, [
            'company_id' => $transaction->company_id,
            'business_document_id' => $document->id,
            'match_type' => $type->value,
            'matched_amount' => $matchedAmount,
        ], $userId);

        return $match;
    }
}
