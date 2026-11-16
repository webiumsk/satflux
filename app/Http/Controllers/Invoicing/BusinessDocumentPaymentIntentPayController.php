<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\BusinessDocumentPaymentIntent;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use Symfony\Component\HttpFoundation\Response;

class BusinessDocumentPaymentIntentPayController extends Controller
{
    public function __construct(protected BusinessDocumentPaymentIntentService $intents) {}

    public function show(string $paymentToken): Response
    {
        $intent = BusinessDocumentPaymentIntent::where('token', $paymentToken)->firstOrFail();

        return $this->landing($intent, '/pay/e/'.$paymentToken, checkDocument: true);
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

        return $this->landing($current, $actionUrl, checkDocument: true, afterActivation: true);
    }

    protected function landing(?BusinessDocumentPaymentIntent $intent, string $actionUrl, bool $unavailable = false, bool $paid = false, ?array $readyTerms = null,
        bool $checkDocument = false, bool $afterActivation = false): Response
    {
        $terms = $intent !== null ? $intent->terms : $readyTerms;
        $state = $this->intents->publicAvailability($intent, unavailable: $unavailable, paid: $paid,
            hasReadyTerms: $readyTerms !== null, checkDocument: $checkDocument, afterActivation: $afterActivation);

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
