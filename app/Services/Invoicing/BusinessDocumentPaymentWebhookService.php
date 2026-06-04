<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class BusinessDocumentPaymentWebhookService
{
    public function __construct(
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleInvoicePayment(string $eventType, array $payload, ?Store $store): bool
    {
        if (! $store) {
            return false;
        }

        if (! in_array($eventType, [
            'InvoiceSettled',
            'InvoicePaymentSettled',
            'InvoiceReceivedPayment',
            'invoice.paid',
        ], true)) {
            return false;
        }

        if ($eventType !== 'InvoiceSettled' && $eventType !== 'invoice.paid') {
            return false;
        }

        $invoiceData = $payload['invoiceData'] ?? $payload['invoice'] ?? $payload;
        $metadata = $invoiceData['metadata'] ?? [];
        $documentId = $metadata['businessDocumentId'] ?? null;

        if (! $documentId) {
            return false;
        }

        $document = BusinessDocument::query()
            ->where('id', $documentId)
            ->where('store_id', $store->id)
            ->first();

        if (! $document) {
            Log::warning('Business document payment webhook: document not found', [
                'business_document_id' => $documentId,
                'store_id' => $store->id,
            ]);

            return false;
        }

        if ($document->status === BusinessDocumentStatus::Paid) {
            return true;
        }

        if ($document->status !== BusinessDocumentStatus::Issued) {
            return false;
        }

        $btcpayStoreId = $payload['storeId'] ?? null;
        if ($btcpayStoreId && $store->btcpay_store_id !== $btcpayStoreId) {
            return false;
        }

        $document->update([
            'status' => BusinessDocumentStatus::Paid,
            'paid_at' => now(),
            'amount_paid' => $document->total,
        ]);

        $this->paymentTokenService->revokeAfterPaid($document->fresh());

        AuditLog::log('business_document.marked_paid', 'business_document', $document->id, [
            'company_id' => $document->company_id,
            'number' => $document->number,
            'source' => 'btcpay_webhook',
            'btcpay_invoice_id' => $invoiceData['id'] ?? null,
        ]);

        return true;
    }
}
