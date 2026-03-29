<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\LightningService;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashuController extends Controller
{
    public function __construct(
        protected CashuService $cashuService,
        protected LightningService $lightningService,
    ) {}

    /**
     * Confirm account password (or LNURL / Nostr challenge) before editing Cashu settings in the UI.
     * Same auth rules as wallet connection reveal, without requiring a wallet_connections row.
     */
    public function confirmEdit(Request $request, Store $store): \Illuminate\Http\JsonResponse
    {
        if (($store->wallet_type ?? null) !== 'cashu') {
            throw ValidationException::withMessages([
                'store' => ['Cashu is not the wallet type for this store.'],
            ]);
        }

        $request->validate([
            'password' => ['nullable', 'string'],
            'confirm_via_lnurl' => ['nullable', 'boolean'],
            'confirm_via_nostr' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $allowed = false;

        if ($request->filled('password')) {
            $allowed = Hash::check($request->password, $user->password);
        } else {
            $cacheKey = 'reveal_confirmed:'.$user->id;
            if (Cache::get($cacheKey)) {
                if ($request->boolean('confirm_via_lnurl') && $user->lightning_public_key) {
                    $allowed = true;
                    Cache::forget($cacheKey);
                } elseif ($request->boolean('confirm_via_nostr') && $user->nostr_public_key) {
                    $allowed = true;
                    Cache::forget($cacheKey);
                }
            }
        }

        if (! $allowed) {
            throw ValidationException::withMessages([
                'password' => [__('auth.invalid_password_or_confirm_lnurl')],
            ]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function getSettings(Store $store): \Illuminate\Http\JsonResponse
    {
        $wt = $store->wallet_type ?? null;

        if ($wt === null || $wt === 'blink' || $wt === 'aqua_boltz') {
            return response()->json([
                'data' => [
                    'mint_url' => null,
                    'lightning_address' => null,
                    'enabled' => true,
                ],
            ]);
        }

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
        $previousWalletType = $store->wallet_type ?? null;
        $switchingFromLightning = in_array($previousWalletType, ['blink', 'aqua_boltz'], true);

        $allowed = $previousWalletType === null
            || $previousWalletType === 'cashu'
            || $switchingFromLightning;

        if (! $allowed) {
            abort(422, 'Cashu is not the wallet type for this store.');
        }

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

        $updated = DB::transaction(function () use ($store, $payload, $userApiKey, $switchingFromLightning) {
            if ($switchingFromLightning) {
                $store->walletConnection()?->delete();
            }

            if (($store->wallet_type ?? null) === null || $switchingFromLightning) {
                $store->update(['wallet_type' => 'cashu']);
                $store->refresh();
            }

            return $this->cashuService->saveSettings($store->btcpay_store_id, $payload, $userApiKey);
        });

        $store->refresh();

        if ($payload['enabled'] ?? true) {
            try {
                $this->lightningService->tryRemoveLightningCheckoutPaymentMethods(
                    $store->btcpay_store_id,
                    $userApiKey
                );
            } catch (\Throwable $e) {
                Log::error('Could not remove Lightning payment methods at BTCPay after Cashu save', [
                    'store_id' => $store->id,
                    'btcpay_store_id' => $store->btcpay_store_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        StoreChecklistService::ensureChecklistInitialized($store);

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

        $items = collect($raw['items'] ?? [])->map(fn (array $item) => $this->formatCashuPaymentItem($item))->values();

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

    /**
     * Map BTCPay Cashu plugin payment row to API shape.
     *
     * The plugin sometimes leaves settlementState as PENDING even after settlement
     * finished; settledAt is the reliable completion timestamp from the plugin.
     */
    private function formatCashuPaymentItem(array $item): array
    {
        $reportedRaw = $item['settlementState'] ?? $item['settlement_state'] ?? null;
        $reported = is_string($reportedRaw) ? strtoupper(trim($reportedRaw)) : null;
        $settledAt = $item['settledAt'] ?? $item['settled_at'] ?? null;
        $hasSettledAt = $settledAt !== null && $settledAt !== '';

        $settlementState = $reported;
        if ($hasSettledAt && $reported === 'PENDING') {
            $settlementState = 'SETTLED';
        }
        if ($hasSettledAt && ($reported === null || $reported === '')) {
            $settlementState = 'SETTLED';
        }

        return [
            'quote_id' => $item['quoteId'] ?? $item['quote_id'] ?? null,
            'invoice_id' => $item['invoiceId'] ?? $item['invoice_id'] ?? null,
            'amount_sats' => $item['amountSats'] ?? $item['amount_sats'] ?? null,
            'state' => $item['state'] ?? null,
            'settlement_state' => $settlementState,
            'settlement_error' => $item['settlementError'] ?? $item['settlement_error'] ?? null,
            'settlement_reference' => $item['settlementReference'] ?? $item['settlement_reference'] ?? null,
            'created_at' => $item['createdAt'] ?? $item['created_at'] ?? null,
            'paid_at' => $item['paidAt'] ?? $item['paid_at'] ?? null,
            'settled_at' => $settledAt,
        ];
    }
}
