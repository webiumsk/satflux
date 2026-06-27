<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class LandingPayButtonController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:1', 'max:20000'],
            'currency' => ['required', 'string', 'in:USD,GBP,EUR,BTC'],
        ]);

        $storeId = config('services.btcpay.landing_pay_demo_store_id');
        if (! is_string($storeId) || $storeId === '') {
            throw new ServiceUnavailableHttpException(null, 'Landing pay button is not configured.');
        }

        /** @var Store|null $store */
        $store = Store::query()->with('user')->find($storeId);
        if (! $store || ! $store->user) {
            throw new ServiceUnavailableHttpException(null, 'Landing pay button store is unavailable.');
        }

        $result = $this->invoiceService->createInvoice(
            $store->btcpay_store_id,
            [
                'amount' => (string) $validated['price'],
                'currency' => $validated['currency'],
                'metadata' => [
                    'source' => 'landing_pay_button',
                ],
            ],
            $store->user->getBtcPayApiKeyOrFail(),
        );

        $checkoutLink = $result['checkoutLink'] ?? null;
        if (! is_string($checkoutLink) || $checkoutLink === '') {
            throw new ServiceUnavailableHttpException(null, 'Could not create checkout link.');
        }

        return redirect()->away($checkoutLink);
    }
}
