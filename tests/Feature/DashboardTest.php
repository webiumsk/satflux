<?php

namespace Tests\Feature;

use App\Models\PosOrder;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $this->app->forgetInstance(\App\Services\BtcPay\BtcPayClient::class);
        Cache::flush();
    }

    /** @test */
    public function unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_without_btcpay_api_key_gets_empty_dashboard(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('stores', [])
            ->assertJsonPath('store_count', 0)
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('revenue_breakdown', []);
    }

    /** @test */
    public function authenticated_user_with_btcpay_api_key_gets_stores_and_revenue(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'btcpay-store-1',
            'name' => 'My Store',
            'wallet_type' => 'blink',
        ]);

        $baseUrl = 'https://btcpay.test';
        Http::fake(function ($request) use ($baseUrl) {
            $url = (string) $request->url();
            if ($request->method() === 'GET' && $url === $baseUrl . '/api/v1/stores') {
                return Http::response([
                    ['id' => 'btcpay-store-1', 'name' => 'My Store', 'defaultCurrency' => 'EUR', 'timeZone' => 'Europe/Vienna', 'created' => 1704067200],
                ], 200);
            }
            return Http::response([], 404);
        });

        // Revenue comes from PosOrder (dashboard uses PosOrder, not invoices)
        $terminal = PosTerminal::create([
            'store_id' => $store->id,
            'name' => 'Terminal 1',
        ]);
        PosOrder::create([
            'pos_terminal_id' => $terminal->id,
            'store_id' => $store->id,
            'amount' => 5000,
            'currency' => 'SATS',
            'status' => PosOrder::STATUS_PAID,
            'paid_method' => PosOrder::PAID_METHOD_LIGHTNING,
            'paid_at' => now(),
        ]);
        PosOrder::create([
            'pos_terminal_id' => $terminal->id,
            'store_id' => $store->id,
            'amount' => 1000,
            'currency' => 'SATS',
            'status' => PosOrder::STATUS_PAID,
            'paid_method' => PosOrder::PAID_METHOD_LIGHTNING,
            'paid_at' => now(),
        ]);
        PosOrder::create([
            'pos_terminal_id' => $terminal->id,
            'store_id' => $store->id,
            'amount' => 50.5,
            'currency' => 'EUR',
            'status' => PosOrder::STATUS_PAID,
            'paid_method' => PosOrder::PAID_METHOD_CARD,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('store_count', 1)
            ->assertJsonPath('stores.0.name', 'My Store')
            ->assertJsonPath('stores.0.wallet_type', 'blink');
        // 5000 + 1000 sats = 6000 total_revenue (EUR not converted to sats)
        $data = $response->json();
        $this->assertSame(6000, (int) $data['total_revenue']);
        $breakdown = $data['revenue_breakdown'] ?? [];
        $eur = collect($breakdown)->firstWhere('currency', 'EUR');
        $this->assertNotNull($eur);
        $this->assertSame(50.5, (float) $eur['amount']);
    }

    /** @test */
    public function dashboard_only_includes_stores_present_in_btcpay_api(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'orphan-store',
            'name' => 'Orphan Store',
        ]);

        $baseUrl = 'https://btcpay.test';
        Http::fake(function ($request) use ($baseUrl) {
            $url = (string) $request->url();
            if ($request->method() === 'GET' && $url === $baseUrl . '/api/v1/stores') {
                // BTCPay returns only one store - orphan-store is not in list
                return Http::response([
                    ['id' => 'other-store', 'name' => 'Other', 'defaultCurrency' => 'EUR', 'timeZone' => 'UTC'],
                ], 200);
            }
            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('store_count', 0)
            ->assertJsonPath('stores', []);
    }

    /** @test */
    public function dashboard_returns_200_when_btcpay_api_fails_uses_empty_stores(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'store-1']);

        $baseUrl = 'https://btcpay.test';
        Http::fake(function ($request) use ($baseUrl) {
            if ($request->method() === 'GET' && (string) $request->url() === $baseUrl . '/api/v1/stores') {
                return Http::response(['error' => 'Unauthorized'], 401);
            }
            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('store_count', 0)
            ->assertJsonPath('stores', [])
            ->assertJsonPath('total_revenue', 0);
    }
}
