<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\BusinessDocument;
use App\Models\Store;
use App\Services\BtcPay\InvoiceService as BtcPayInvoiceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BusinessDocumentBtcPayService
{
    public function __construct(
        protected BtcPayInvoiceService $btcPayInvoiceService,
    ) {}

    /**
     * Attach or refresh BTCPay checkout for an issued document when Bitcoin payment is enabled.
     */
    public function syncForDocument(BusinessDocument $document, bool $forceRefresh = false): void
    {
        $document->loadMissing(['company', 'store']);

        if (! $document->payment_btc_enabled || ! $document->store_id) {
            if ($document->btcpay_invoice_id || $document->btcpay_checkout_link) {
                $document->btcpay_invoice_id = null;
                $document->btcpay_checkout_link = null;
                $document->save();
            }

            return;
        }

        if ($this->syncPaidFromBtcpayIfSettled($document)) {
            return;
        }

        if (
            ! $forceRefresh
            && $document->btcpay_invoice_id
            && $document->btcpay_checkout_link
            && ! $this->shouldRefreshCheckout($document)
        ) {
            return;
        }

        $this->attachCheckout($document);
        $document->save();
    }

    /**
     * If BTCPay shows the linked invoice as paid, mark the business document paid locally.
     */
    public function syncPaidFromBtcpayIfSettled(BusinessDocument $document): bool
    {
        if ($document->status === BusinessDocumentStatus::Paid) {
            return true;
        }

        if ($document->status !== BusinessDocumentStatus::Issued || ! $document->btcpay_invoice_id) {
            return false;
        }

        $document->loadMissing(['store.user']);

        $invoice = $this->fetchBtcpayInvoice($document);
        if ($invoice === null || ! $this->invoiceIndicatesPaid($invoice)) {
            return false;
        }

        app(BusinessDocumentMarkPaidService::class)->markPaid(
            $document,
            (float) $document->total,
            null,
            'btcpay_sync',
        );

        return true;
    }

    /**
     * Resolve business document for a BTCPay invoice (DB id, then API metadata).
     */
    public function resolveDocumentForBtcpayInvoice(Store $store, string $invoiceId): ?BusinessDocument
    {
        $document = BusinessDocument::query()
            ->where('store_id', $store->id)
            ->where('btcpay_invoice_id', $invoiceId)
            ->first();

        if ($document) {
            return $document;
        }

        $invoice = $this->fetchBtcpayInvoiceForStore($store, $invoiceId);
        if ($invoice === null) {
            return null;
        }

        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];

        foreach (['businessDocumentId', 'business_document_id'] as $key) {
            $documentId = $metadata[$key] ?? null;
            if (! is_string($documentId) || $documentId === '') {
                continue;
            }

            $document = BusinessDocument::query()
                ->where('id', $documentId)
                ->where('store_id', $store->id)
                ->first();

            if ($document) {
                if (! $document->btcpay_invoice_id) {
                    $document->update([
                        'btcpay_invoice_id' => $invoiceId,
                        'btcpay_checkout_link' => $invoice['checkoutLink'] ?? $document->btcpay_checkout_link,
                    ]);
                }

                return $document->fresh();
            }
        }

        return null;
    }

    public function shouldRefreshAfterUpdate(
        BusinessDocument $document,
        float $previousTotal,
        ?string $previousStoreId,
        bool $previousPaymentBtcEnabled
    ): bool {
        if (! $document->payment_btc_enabled || ! $document->store_id) {
            return false;
        }

        if (! $previousPaymentBtcEnabled || ! $document->btcpay_invoice_id) {
            return true;
        }

        if ($previousStoreId !== $document->store_id) {
            return true;
        }

        return abs((float) $document->total - $previousTotal) > 0.001;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function invoiceIndicatesPaid(array $invoice): bool
    {
        $status = (string) ($invoice['status'] ?? '');
        $additional = (string) ($invoice['additionalStatus'] ?? '');

        if ($additional === 'PaidPartial') {
            return false;
        }

        return in_array($status, ['Settled', 'Processing'], true);
    }

    protected function shouldRefreshCheckout(BusinessDocument $document): bool
    {
        if (! $document->btcpay_invoice_id) {
            return true;
        }

        $invoice = $this->fetchBtcpayInvoice($document);
        if ($invoice === null) {
            return true;
        }

        if ($this->invoiceIndicatesPaid($invoice)) {
            return false;
        }

        return in_array((string) ($invoice['status'] ?? ''), ['Expired', 'Invalid'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchBtcpayInvoice(BusinessDocument $document): ?array
    {
        $store = $document->store;
        if (! $store || ! $document->btcpay_invoice_id) {
            return null;
        }

        return $this->fetchBtcpayInvoiceForStore($store, $document->btcpay_invoice_id);
    }

    /**
     * BTCPay-side state of an existing ephemeral checkout (fresh fetch):
     *  - paid: the invoice settled - the caller must surface the payment,
     *    NEVER replace it with a fresh invoice (PR #144 root cause),
     *  - payable: New/Processing - reusable instead of minting another one
     *    (Processing means the customer already broadcast the payment, so
     *    the link is reused and settlement lands via webhook/polling - it
     *    is deliberately NOT reported paid here),
     *  - replaceable: expired/invalid - a fresh invoice may be created,
     *  - unknown: BTCPay could not be reached / did not answer - the caller
     *    must fail safe and never mint a replacement blindly.
     *
     * @return array{state: 'paid'|'payable'|'replaceable'|'unknown', checkout_link: string|null, btcpay_invoice_id: string}
     */
    public function ephemeralCheckoutState(Store $store, string $btcpayInvoiceId): array
    {
        $invoice = $this->fetchBtcpayInvoiceForStore($store, $btcpayInvoiceId);
        if (! is_array($invoice)) {
            return [
                'state' => 'unknown',
                'checkout_link' => null,
                'btcpay_invoice_id' => $btcpayInvoiceId,
            ];
        }

        $checkoutLink = isset($invoice['checkoutLink']) ? (string) $invoice['checkoutLink'] : null;
        $status = strtolower((string) ($invoice['status'] ?? ''));

        // Payable takes precedence: invoiceIndicatesPaid() counts Processing
        // as paid for the webhook/settlement flows, but an unconfirmed
        // payment must not mark the document paid here.
        if (in_array($status, ['new', 'processing'], true)) {
            return [
                'state' => 'payable',
                'checkout_link' => $checkoutLink,
                'btcpay_invoice_id' => $btcpayInvoiceId,
            ];
        }

        return [
            'state' => $this->invoiceIndicatesPaid($invoice) ? 'paid' : 'replaceable',
            'checkout_link' => $checkoutLink,
            'btcpay_invoice_id' => $btcpayInvoiceId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchBtcpayInvoiceForStore(Store $store, string $invoiceId): ?array
    {
        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $this->btcPayInvoiceService->forgetInvoiceCache(
                $store->btcpay_store_id,
                $invoiceId,
                $userApiKey,
            );

            return $this->btcPayInvoiceService->getInvoice(
                $store->btcpay_store_id,
                $invoiceId,
                $userApiKey,
            );
        } catch (\Throwable $e) {
            Log::warning('Business document: failed to fetch BTCPay invoice', [
                'store_id' => $store->id,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function attachCheckout(BusinessDocument $document): void
    {
        /** @var Store|null $store */
        $store = $document->store;
        if (! $store || $store->company_id !== $document->company_id || $store->user_id !== $document->company->user_id) {
            throw ValidationException::withMessages([
                'store_id' => ['A valid store linked to this company is required for Bitcoin payments.'],
            ]);
        }

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $result = $this->btcPayInvoiceService->createInvoice(
            $store->btcpay_store_id,
            [
                'amount' => (string) $document->total,
                'currency' => $document->currency,
                'metadata' => [
                    'businessDocumentId' => $document->id,
                    'companyId' => $document->company_id,
                    'documentNumber' => $document->number,
                ],
                'checkout' => [
                    'expirationMinutes' => 60,
                    'monitoringMinutes' => 1440,
                ],
            ],
            $userApiKey
        );

        $document->btcpay_invoice_id = $result['id'] ?? null;
        $document->btcpay_checkout_link = $result['checkoutLink'] ?? null;
        $document->btcpay_checkout_created_at = now();
    }

    /**
     * Create a BTCPay checkout for an in-memory document without persisting business document rows.
     *
     * @return array{checkout_link: string|null, btcpay_invoice_id: string|null}
     */
    public function createEphemeralCheckout(BusinessDocument $document, Store $store, ?string $evoluDocumentId = null): array
    {
        if (! $document->payment_btc_enabled) {
            throw ValidationException::withMessages([
                'document' => ['Bitcoin payment is not enabled for this document.'],
            ]);
        }

        if ($store->user_id !== $document->company?->user_id) {
            throw ValidationException::withMessages([
                'store_id' => ['The selected store does not belong to your account.'],
            ]);
        }

        $document->store_id = $store->id;
        $document->setRelation('store', $store);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $metadata = [
            'ephemeral' => true,
            'documentNumber' => $document->number,
        ];

        if (is_string($evoluDocumentId) && $evoluDocumentId !== '') {
            $metadata['evoluDocumentId'] = $evoluDocumentId;
        }

        $result = $this->btcPayInvoiceService->createInvoice(
            $store->btcpay_store_id,
            [
                'amount' => (string) $document->total,
                'currency' => $document->currency,
                'metadata' => $metadata,
                'checkout' => [
                    'expirationMinutes' => 60,
                    'monitoringMinutes' => 1440,
                ],
            ],
            $userApiKey
        );

        return [
            'checkout_link' => $result['checkoutLink'] ?? null,
            'btcpay_invoice_id' => $result['id'] ?? null,
        ];
    }
}
