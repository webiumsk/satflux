<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashuPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_payments_normalizes_pending_to_settled_when_settled_at_present(): void
    {
        $baseUrl = rtrim(config('services.btcpay.base_url'), '/');
        $btcpaySid = 'store-cashu-payments';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpaySid) {
            if (! str_contains($request->url(), "{$baseUrl}/api/v1/stores/{$btcpaySid}/plugins/cashumelt/payments")) {
                return Http::response(['error' => 'unexpected URL'], 500);
            }

            return Http::response([
                'total' => 1,
                'offset' => 0,
                'limit' => 50,
                'items' => [[
                    'quoteId' => 'q-normalize',
                    'invoiceId' => 'inv-1',
                    'amountSats' => 16771,
                    'state' => 'PAID',
                    'settlementState' => 'PENDING',
                    'settlementError' => null,
                    'createdAt' => '2026-03-26T22:54:00Z',
                    'paidAt' => '2026-03-26T22:55:00Z',
                    'settledAt' => '2026-03-26T22:56:00Z',
                ]],
            ], 200);
        });

        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'wallet_type' => 'cashu',
            'btcpay_store_id' => $btcpaySid,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/cashu/payments");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.settlement_state', 'SETTLED');
        $response->assertJsonPath('data.items.0.settled_at', '2026-03-26T22:56:00Z');
    }

    public function test_list_payments_keeps_pending_when_settled_at_missing(): void
    {
        $baseUrl = rtrim(config('services.btcpay.base_url'), '/');
        $btcpaySid = 'store-cashu-payments-2';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpaySid) {
            if (! str_contains($request->url(), "{$baseUrl}/api/v1/stores/{$btcpaySid}/plugins/cashumelt/payments")) {
                return Http::response(['error' => 'unexpected URL'], 500);
            }

            return Http::response([
                'total' => 1,
                'offset' => 0,
                'limit' => 50,
                'items' => [[
                    'quoteId' => 'q-pending',
                    'invoiceId' => 'inv-2',
                    'amountSats' => 100,
                    'state' => 'PENDING',
                    'settlementState' => 'PENDING',
                    'settlementError' => null,
                    'createdAt' => '2026-03-26T10:00:00Z',
                    'paidAt' => null,
                    'settledAt' => null,
                ]],
            ], 200);
        });

        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'wallet_type' => 'cashu',
            'btcpay_store_id' => $btcpaySid,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/cashu/payments");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.settlement_state', 'PENDING');
    }

    public function test_cashu_confirm_edit_accepts_account_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'wallet_type' => 'cashu',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/stores/{$store->id}/cashu/confirm-edit", [
            'password' => 'wrong',
        ])->assertUnprocessable();

        $this->postJson("/api/stores/{$store->id}/cashu/confirm-edit", [
            'password' => 'correct-password',
        ])->assertOk()->assertJsonPath('data.ok', true);
    }

    public function test_cashu_confirm_edit_rejects_when_wallet_type_is_not_cashu(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret'),
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'wallet_type' => 'blink',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/stores/{$store->id}/cashu/confirm-edit", [
            'password' => 'secret',
        ])->assertUnprocessable();
    }
}
