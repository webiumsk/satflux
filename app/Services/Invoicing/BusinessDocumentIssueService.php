<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Support\Invoicing\BuyerSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDocumentIssueService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
        protected BusinessDocumentBtcPayService $btcPayService,
        protected ComplianceSubmissionService $complianceSubmissionService,
        protected CompanyStockMovementService $stockMovementService,
    ) {}

    public function issue(BusinessDocument $document): BusinessDocument
    {
        $issued = DB::transaction(function () use ($document) {
            $lockedDocument = BusinessDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDocument->load(['company', 'lines', 'store', 'contact']);

            if (! $lockedDocument->canIssue()) {
                throw ValidationException::withMessages([
                    'status' => ['Document cannot be issued in its current status.'],
                ]);
            }

            $number = $this->sequenceService->nextNumber(
                $lockedDocument->company,
                $lockedDocument->type->value
            );

            $lockedDocument->number = $number;
            $lockedDocument->variable_symbol = $lockedDocument->variable_symbol ?: preg_replace('/\D/', '', $number);
            $lockedDocument->issue_date = $lockedDocument->issue_date ?? now()->toDateString();
            $lockedDocument->status = BusinessDocumentStatus::Issued;

            if ($lockedDocument->contact) {
                $lockedDocument->buyer_snapshot = BuyerSnapshot::fromContact($lockedDocument->contact);
            }

            if ($lockedDocument->type === BusinessDocumentType::Quote) {
                $lockedDocument->quote_status = BusinessDocumentQuoteStatus::Pending;
            }

            $lockedDocument->btcpay_invoice_id = null;
            $lockedDocument->btcpay_checkout_link = null;
            $lockedDocument->btcpay_checkout_created_at = null;
            $this->paymentTokenService->assignIfNeeded($lockedDocument);

            $lockedDocument->save();

            if ($lockedDocument->payment_btc_enabled && $lockedDocument->store_id) {
                try {
                    $this->btcPayService->syncForDocument($lockedDocument->fresh(), forceRefresh: true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            AuditLog::log('business_document.issued', 'business_document', $lockedDocument->id, [
                'company_id' => $lockedDocument->company_id,
                'number' => $lockedDocument->number,
            ]);

            $lockedDocument = $lockedDocument->fresh(['lines', 'contact', 'store', 'company']);
            $this->stockMovementService->applyDocumentIssue($lockedDocument);

            return $lockedDocument;
        });

        $this->complianceSubmissionService->queueIfEligible($issued);

        return $issued;
    }
}
