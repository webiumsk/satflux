<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentPaymentIntent;
use App\Models\Company;
use App\Models\Store;
use Symfony\Component\HttpFoundation\Response;

class BusinessDocumentPayController extends BusinessDocumentPaymentIntentPayController
{
    public function show(string $paymentToken): Response
    {
        $document = $this->document($paymentToken, allowUnassigned: true);
        if ($document->hasStatus(BusinessDocumentStatus::Paid)) {
            return $this->landing(null, '', paid: true);
        }
        if ($document->store?->getAttribute('company_id') === null) {
            // Historical links reached a generic error page when an owned store was detached.
            // Preserve that read-only response without granting payment authority to the unassigned store.
            return $this->landing(null, '', unavailable: true);
        }
        $intent = BusinessDocumentPaymentIntent::where('business_document_id', $document->id)->first();

        return $this->landing($intent, '/pay/i/'.$paymentToken,
            unavailable: $intent !== null && ! $this->intents->matchesDocument($intent, $document),
            readyTerms: $intent === null ? $this->intents->terms($document) : null);
    }

    public function activate(string $paymentToken): Response
    {
        $document = $this->document($paymentToken);
        if ($document->hasStatus(BusinessDocumentStatus::Paid)) {
            return $this->landing(null, '', paid: true);
        }
        $store = $document->store;
        abort_unless($store instanceof Store, 404);
        $intent = $this->intents->prepare($document, $store);

        return $intent ? $this->activateIntent($intent, '/pay/i/'.$paymentToken, $paymentToken) : $this->landing(null, '');
    }

    private function document(string $paymentToken, bool $allowUnassigned = false): BusinessDocument
    {
        $document = BusinessDocument::where('payment_token', $paymentToken)->with(['company', 'store.user'])->firstOrFail();
        $store = $document->store;
        $company = $document->company;
        abort_unless($document->hasStatus(BusinessDocumentStatus::Paid) || (
            $document->hasStatus(BusinessDocumentStatus::Issued) && $document->payment_btc_enabled
            && $store instanceof Store && $company instanceof Company
            && $store->user_id === $company->user_id
            && ($store->company_id === $company->id || ($allowUnassigned && $store->company_id === null))
        ), 404);

        return $document;
    }
}
