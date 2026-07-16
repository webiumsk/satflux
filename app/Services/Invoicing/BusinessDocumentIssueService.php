<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Support\Invoicing\BankSymbolNormalizer;
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
        // Lock + re-check INSIDE the transaction: two concurrent issue calls
        // both passing an unlocked canIssue() would burn two numbers and
        // apply stock twice (Cursor PR #64).
        $issued = DB::transaction(function () use ($document) {
            $document = BusinessDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $document->load(['company', 'lines', 'store', 'contact']);

            if (! $document->canIssue()) {
                throw ValidationException::withMessages([
                    'status' => ['Document cannot be issued in its current status.'],
                ]);
            }

            $number = $this->sequenceService->nextNumber(
                $document->company,
                $document->type->value
            );

            $document->number = $number;
            $document->variable_symbol = BankSymbolNormalizer::variableSymbol($document->variable_symbol)
                ?? BankSymbolNormalizer::variableSymbol($number);
            $document->issue_date = $document->issue_date ?? now()->toDateString();
            $document->status = BusinessDocumentStatus::Issued;

            if ($document->contact) {
                $document->buyer_snapshot = BuyerSnapshot::fromContact($document->contact);
            }

            if ($document->type === BusinessDocumentType::Quote) {
                $document->quote_status = BusinessDocumentQuoteStatus::Pending;
            }

            $document->btcpay_invoice_id = null;
            $document->btcpay_checkout_link = null;
            $document->btcpay_checkout_created_at = null;
            $this->paymentTokenService->assignIfNeeded($document);

            $document->save();

            if ($document->payment_btc_enabled && $document->store_id) {
                try {
                    $this->btcPayService->syncForDocument($document->fresh(), forceRefresh: true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            AuditLog::log('business_document.issued', 'business_document', $document->id, [
                'company_id' => $document->company_id,
                'number' => $document->number,
            ]);

            $document = $document->fresh(['lines', 'contact', 'store', 'company']);
            $this->stockMovementService->applyDocumentIssue($document);

            return $document;
        });

        $this->complianceSubmissionService->queueIfEligible($issued);

        // The transaction worked on a locked re-fetch - sync the caller's
        // instance so the pre-lock contract (in-place mutation) holds.
        $document->refresh();

        return $issued;
    }
}
