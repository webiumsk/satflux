<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use App\Services\Invoicing\BusinessDocumentBtcPayService;
use App\Services\Invoicing\BusinessDocumentPaymentTokenService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BusinessDocumentPayController extends Controller
{
    public function __construct(
        protected BusinessDocumentBtcPayService $btcPayService,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
    ) {}

    public function show(Request $request, string $paymentToken): View|Response
    {
        $document = BusinessDocument::query()
            ->where('payment_token', $paymentToken)
            ->with(['company', 'contact', 'store.user'])
            ->first();

        if (! $document) {
            abort(404);
        }

        if ($document->status === BusinessDocumentStatus::Paid) {
            return view('pay.business-invoice-paid', [
                'document' => $document,
            ]);
        }

        if (
            $document->status !== BusinessDocumentStatus::Issued
            || ! $document->payment_btc_enabled
            || ! $document->store_id
        ) {
            abort(404);
        }

        try {
            $this->btcPayService->syncForDocument($document, forceRefresh: true);
        } catch (\Throwable $e) {
            return view('pay.business-invoice-error', [
                'document' => $document,
                'message' => $e->getMessage(),
            ]);
        }

        $checkoutLink = $document->btcpay_checkout_link;
        if (! $checkoutLink) {
            return view('pay.business-invoice-error', [
                'document' => $document,
                'message' => __('messages.business_invoice_pay_checkout_failed'),
            ]);
        }

        return view('pay.business-invoice-redirect', [
            'document' => $document,
            'checkoutLink' => $checkoutLink,
        ]);
    }
}
