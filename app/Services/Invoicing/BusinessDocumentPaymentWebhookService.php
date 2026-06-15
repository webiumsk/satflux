<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\BusinessDocument;
use App\Models\Store;
use App\Support\BtcPay\BtcPayWebhookEventType;
use Illuminate\Support\Facades\Log;

class BusinessDocumentPaymentWebhookService
{
    public function __construct(
        protected BusinessDocumentMarkPaidService $markPaidService,
        protected BusinessDocumentBtcPayService $btcPayService,
        protected EphemeralBtcpayCheckoutService $ephemeralCheckoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleInvoicePayment(string $eventType, array $payload, ?Store $store): bool
    {
        if (! $store) {
            return false;
        }

        if (! BtcPayWebhookEventType::shouldMarkBusinessDocumentPaid($eventType)) {
            return false;
        }

        $metadata = $this->extractMetadata($payload);
        $invoiceId = $this->extractInvoiceId($payload);
        if ($invoiceId && $this->ephemeralCheckoutService->metadataIndicatesEphemeral($metadata)) {
            $ephemeral = $this->ephemeralCheckoutService->markPaidFromWebhook($store, $invoiceId, $metadata);
            if ($ephemeral) {
                Log::info('Ephemeral BTCPay checkout marked paid from webhook', [
                    'evolu_document_id' => $ephemeral->evolu_document_id,
                    'store_id' => $store->id,
                    'btcpay_invoice_id' => $invoiceId,
                    'event_type' => BtcPayWebhookEventType::normalize($eventType),
                ]);

                return true;
            }
        }

        $document = $this->resolveDocument($payload, $store);

        if (! $document) {
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

        if ($this->btcPayService->syncPaidFromBtcpayIfSettled($document->fresh())) {
            Log::info('Business document marked paid from BTCPay webhook', [
                'business_document_id' => $document->id,
                'store_id' => $store->id,
                'event_type' => BtcPayWebhookEventType::normalize($eventType),
            ]);

            return true;
        }

        $this->markPaidService->markPaid(
            $document,
            (float) $document->total,
            null,
            'btcpay_webhook',
        );

        Log::info('Business document marked paid from BTCPay webhook (event only)', [
            'business_document_id' => $document->id,
            'store_id' => $store->id,
            'event_type' => BtcPayWebhookEventType::normalize($eventType),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveDocument(array $payload, Store $store): ?BusinessDocument
    {
        $documentId = $this->extractBusinessDocumentId($payload);

        if ($documentId) {
            $document = BusinessDocument::query()
                ->where('id', $documentId)
                ->where('store_id', $store->id)
                ->first();

            if ($document) {
                return $document;
            }

            Log::warning('Business document payment webhook: document not found by metadata', [
                'business_document_id' => $documentId,
                'store_id' => $store->id,
            ]);
        }

        $invoiceId = $this->extractInvoiceId($payload);
        if (! $invoiceId) {
            Log::debug('Business document payment webhook: no document id or invoice id in payload');

            return null;
        }

        return $this->btcPayService->resolveDocumentForBtcpayInvoice($store, $invoiceId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractBusinessDocumentId(array $payload): ?string
    {
        $metadata = $this->extractMetadata($payload);

        foreach (['businessDocumentId', 'business_document_id'] as $key) {
            $value = $metadata[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extractMetadata(array $payload): array
    {
        $nested = $payload['invoice'] ?? $payload['invoiceData'] ?? null;
        if (is_array($nested) && is_array($nested['metadata'] ?? null)) {
            return $nested['metadata'];
        }

        if (is_array($payload['metadata'] ?? null)) {
            return $payload['metadata'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractInvoiceId(array $payload): ?string
    {
        $candidates = [
            $payload['invoiceId'] ?? null,
            $payload['invoice_id'] ?? null,
        ];

        $nested = $payload['invoice'] ?? $payload['invoiceData'] ?? null;
        if (is_array($nested)) {
            $candidates[] = $nested['id'] ?? null;
            $candidates[] = $nested['invoiceId'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
