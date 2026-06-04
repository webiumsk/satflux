<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Store;
use App\Services\BtcPay\InvoiceService as BtcPayInvoiceService;
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

        if (
            ! $forceRefresh
            && $document->btcpay_invoice_id
            && $document->btcpay_checkout_link
        ) {
            return;
        }

        $this->attachCheckout($document);
        $document->save();
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
}
