<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentPaymentIntent;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use Symfony\Component\HttpFoundation\Response;

class BusinessDocumentPaymentIntentPayController extends Controller
{
    public function __construct(protected BusinessDocumentPaymentIntentService $intents) {}

    public function show(string $paymentToken): Response
    {
        $intent = BusinessDocumentPaymentIntent::where('token', $paymentToken)->firstOrFail();

        $unavailable = false;
        $paid = false;
        if (str_starts_with($intent->document_key, 'i:')) {
            $document = BusinessDocument::with(['company', 'store'])->find($intent->business_document_id);
            $paid = $document?->hasStatus(BusinessDocumentStatus::Paid) ?? false;
            $unavailable = ! $document || (! $paid && ! $this->intents->matchesDocument($intent, $document));
        }

        return $this->landing($intent, '/pay/e/'.$paymentToken, unavailable: $unavailable, paid: $paid);
    }

    public function activate(string $paymentToken): Response
    {
        $intent = BusinessDocumentPaymentIntent::where('token', $paymentToken)->firstOrFail();

        return $this->activateIntent($intent, '/pay/e/'.$paymentToken);
    }

    protected function activateIntent(BusinessDocumentPaymentIntent $intent, string $actionUrl, ?string $legacyToken = null): Response
    {
        try {
            $result = $this->intents->activate($intent, $legacyToken);
            if ($this->intents->safeCheckoutUrl($result['checkout_link'] ?? null)) {
                return redirect()->away($result['checkout_link'], 303)->withHeaders($this->headers());
            }
        } catch (\Throwable) {
            // Public bearer links never expose provider/credential exception details.
        }

        $current = $intent->fresh();
        $unavailable = ! in_array($current->state, ['active', 'paid'], true);
        if (str_starts_with($current->document_key, 'i:')) {
            $document = BusinessDocument::with(['company', 'store'])->find($current->business_document_id);
            $unavailable = $unavailable || ! $document || (! $document->hasStatus(BusinessDocumentStatus::Paid)
                && ! $this->intents->matchesDocument($current, $document));
        }

        return $this->landing($current, $actionUrl, unavailable: $unavailable);
    }

    protected function landing(?BusinessDocumentPaymentIntent $intent, string $actionUrl, bool $unavailable = false, bool $paid = false, ?array $readyTerms = null): Response
    {
        $terms = $intent !== null ? $intent->terms : $readyTerms;
        $state = ! $unavailable && ($paid || $intent?->state === 'paid') ? 'paid' : 'unavailable';
        if ($intent === null && $readyTerms !== null && ! $unavailable) {
            $state = 'ready';
        }
        if (! $unavailable && $intent?->state === 'active'
            && $intent->store()->where('user_id', $intent->user_id)->exists()) {
            $state = 'ready';
        }

        return response()->view('pay.business-invoice-lazy', [
            'state' => $state, 'actionUrl' => $state === 'ready' ? $actionUrl : null,
            'amount' => $terms['amount'] ?? null, 'currency' => $terms['currency'] ?? null,
            'number' => $terms['number'] ?? null, 'error' => null,
        ])->withHeaders($this->headers());
    }

    protected function headers(): array
    {
        return ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer'];
    }
}
