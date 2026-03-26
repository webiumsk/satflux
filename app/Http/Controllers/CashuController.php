<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\CashuService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashuController extends Controller
{
    public function __construct(protected CashuService $cashuService)
    {
    }

    public function getSettings(Store $store): \Illuminate\Http\JsonResponse
    {
        $this->ensureCashuStore($store);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $settings = $this->cashuService->getSettings($store->btcpay_store_id, $userApiKey);

        return response()->json([
            'data' => [
                'mint_url' => $settings['mintUrl'] ?? null,
                'lightning_address' => $settings['lightningAddress'] ?? null,
                'enabled' => $settings['enabled'] ?? true,
            ],
        ]);
    }

    public function updateSettings(Request $request, Store $store): \Illuminate\Http\JsonResponse
    {
        $this->ensureCashuStore($store);

        $request->validate([
            'mint_url' => ['required', 'string', 'url', 'starts_with:https://'],
            'lightning_address' => ['required', 'string', 'regex:/^[^@]+@[^@]+$/'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $payload = [
            'mintUrl' => $request->mint_url,
            'lightningAddress' => $request->lightning_address,
            'enabled' => $request->boolean('enabled', true),
        ];

        $updated = $this->cashuService->saveSettings($store->btcpay_store_id, $payload, $userApiKey);

        return response()->json([
            'data' => [
                'mint_url' => $updated['mintUrl'] ?? $request->mint_url,
                'lightning_address' => $updated['lightningAddress'] ?? $request->lightning_address,
                'enabled' => $updated['enabled'] ?? ($payload['enabled'] ?? true),
            ],
        ]);
    }

    public function listPayments(Request $request, Store $store): \Illuminate\Http\JsonResponse
    {
        $this->ensureCashuStore($store);

        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'settlementState' => ['sometimes', 'string', Rule::in(['SETTLED', 'PENDING', 'FAILED'])],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $limit = (int) $request->input('limit', 50);
        $offset = (int) $request->input('offset', 0);

        $params = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        $settlementState = $request->input('settlementState');
        if ($settlementState) {
            $params['settlementState'] = $settlementState;
        }

        $raw = $this->cashuService->listPayments($store->btcpay_store_id, $userApiKey, $params);

        $items = collect($raw['items'] ?? [])->map(function (array $item) {
            return [
                'quote_id' => $item['quoteId'] ?? null,
                'invoice_id' => $item['invoiceId'] ?? null,
                'amount_sats' => $item['amountSats'] ?? null,
                'state' => $item['state'] ?? null,
                'settlement_state' => $item['settlementState'] ?? null,
                'settlement_error' => $item['settlementError'] ?? null,
                'settlement_reference' => $item['settlementReference'] ?? null,
                'created_at' => $item['createdAt'] ?? null,
                'paid_at' => $item['paidAt'] ?? null,
                'settled_at' => $item['settledAt'] ?? null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'total' => (int) ($raw['total'] ?? 0),
                'offset' => (int) ($raw['offset'] ?? $offset),
                'limit' => (int) ($raw['limit'] ?? $limit),
                'items' => $items,
            ],
        ]);
    }

    public function retryPayment(Request $request, Store $store, string $quoteId): \Illuminate\Http\JsonResponse
    {
        $this->ensureCashuStore($store);

        $request->validate([
            'quoteId' => ['sometimes', 'string'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $result = $this->cashuService->retryPayment($store->btcpay_store_id, $quoteId, $userApiKey);

        return response()->json([
            'data' => [
                'settled' => $result['settled'] ?? null,
                'error' => $result['error'] ?? null,
            ],
        ]);
    }

    protected function ensureCashuStore(Store $store): void
    {
        if (($store->wallet_type ?? null) !== 'cashu') {
            abort(404, 'Cashu wallet is not configured for this store.');
        }
    }
}

