<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Services\Integrations\WooCommerceWebhookNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDocumentMarkPaidService
{
    public function __construct(
        protected BusinessDocumentIssueService $issueService,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
    ) {}

    /**
     * Mark document paid (issues draft first). Supports partial bank amount.
     */
    public function markPaid(
        BusinessDocument $document,
        ?float $amountPaid = null,
        ?BankTransaction $bankTransaction = null,
        string $source = 'manual',
        ?int $userId = null,
    ): BusinessDocument {
        $notifyPaid = DB::transaction(function () use ($document, $amountPaid, $bankTransaction, $source, $userId): bool {
            $current = BusinessDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            if ($current->getAttribute('status') === BusinessDocumentStatus::Draft) {
                $current = $this->issueService->issue($current);
            }

            if ($current->getAttribute('status') === BusinessDocumentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => ['Cancelled documents cannot be marked as paid.'],
                ]);
            }

            if ($current->getAttribute('status') === BusinessDocumentStatus::Paid) {
                return false;
            }

            if ($current->getAttribute('status') !== BusinessDocumentStatus::Issued) {
                throw ValidationException::withMessages([
                    'status' => ['Only issued documents can be marked as paid.'],
                ]);
            }

            $paidAmount = $amountPaid ?? (float) $current->total;
            $total = (float) $current->total;
            $fullyPaid = abs($paidAmount - $total) <= (float) config('bank_import.amount_tolerance', 0.01);

            $current->update([
                'status' => $fullyPaid ? BusinessDocumentStatus::Paid : BusinessDocumentStatus::Issued,
                'paid_at' => $fullyPaid ? now() : $current->paid_at,
                'amount_paid' => round($paidAmount, 2),
                'paid_payment_method' => $fullyPaid ? match ($source) {
                    'btcpay_sync', 'subscription_webhook' => 'btcpay',
                    'bank_auto', 'bank_manual' => 'bank_transfer',
                    'manual', 'bulk_manual' => 'manual',
                    default => null,
                } : $current->paid_payment_method,
            ]);

            if ($fullyPaid) {
                $this->paymentTokenService->revokeAfterPaid($current);
            }

            $metadata = [
                'company_id' => $current->company_id,
                'number' => $current->number,
                'source' => $source,
                'amount_paid' => $paidAmount,
            ];
            if ($bankTransaction) {
                $metadata['bank_transaction_id'] = $bankTransaction->id;
            }

            AuditLog::log(
                'business_document.marked_paid',
                'business_document',
                $current->id,
                $metadata,
                $userId,
            );

            return $fullyPaid;
        });

        // Preserve the service's in-place caller state while using the locked row
        // as the authority. External notification waits for all enclosing locks.
        $document->refresh();
        if ($notifyPaid) {
            $documentId = $document->getKey();
            DB::afterCommit(function () use ($documentId): void {
                $current = BusinessDocument::query()->find($documentId);
                if ($current?->getAttribute('status') === BusinessDocumentStatus::Paid) {
                    app(WooCommerceWebhookNotifier::class)->notifyDocumentPaid($current);
                }
            });
        }

        return $document->load(['contact', 'store']);
    }

    public function unmarkPaid(BusinessDocument $document, ?int $userId = null): BusinessDocument
    {
        DB::transaction(function () use ($document, $userId): void {
            $current = BusinessDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            if ($current->getAttribute('status') !== BusinessDocumentStatus::Paid) {
                return;
            }

            $current->update([
                'status' => BusinessDocumentStatus::Issued,
                'paid_at' => null,
                'amount_paid' => null,
                'paid_payment_method' => null,
            ]);

            AuditLog::log('business_document.unmarked_paid', 'business_document', $current->id, [
                'company_id' => $current->company_id,
                'number' => $current->number,
            ], $userId);
        });

        $document->refresh();

        return $document->load(['contact', 'store']);
    }
}
