<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDocumentIssueService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
        protected BusinessDocumentBtcPayService $btcPayService,
    ) {}

    public function issue(BusinessDocument $document): BusinessDocument
    {
        if (! $document->canIssue()) {
            throw ValidationException::withMessages([
                'status' => ['Document cannot be issued in its current status.'],
            ]);
        }

        $document->load(['company', 'lines', 'store']);

        return DB::transaction(function () use ($document) {
            $number = $this->sequenceService->nextNumber(
                $document->company,
                $document->type->value
            );

            $document->number = $number;
            $document->variable_symbol = $document->variable_symbol ?: preg_replace('/\D/', '', $number);
            $document->issue_date = $document->issue_date ?? now()->toDateString();
            $document->status = BusinessDocumentStatus::Issued;

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

            return $document->fresh(['lines', 'contact', 'store', 'company']);
        });
    }
}
