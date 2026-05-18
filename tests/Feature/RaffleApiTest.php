<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RaffleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_returns_available_when_plugin_responds(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle")) {
                return Http::response([], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/raffles/status?refresh=1");

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
    }

    public function test_status_returns_unavailable_when_plugin_missing(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle")) {
                return Http::response('Not found', 404);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/raffles/status?refresh=1");

        $response->assertOk();
        $response->assertJsonPath('data.available', false);
    }

    public function test_list_returns_plugin_unavailable_on_404(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle")) {
                return Http::response('Not found', 404);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/raffles");

        $response->assertNotFound();
        $response->assertJsonPath('code', 'raffle_plugin_unavailable');
    }

    public function test_create_and_open_raffle(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = 'raffle-uuid-1';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle")) {
                return Http::response([
                    'id' => $raffleId,
                    'name' => 'Spring Raffle',
                    'description' => null,
                    'storeId' => $btcpayStoreId,
                    'ticketPriceSats' => 21000,
                    'maxTickets' => 100,
                    'status' => 'Draft',
                    'ticketsSold' => 0,
                    'createdAt' => '2026-05-18T12:00:00Z',
                ], 201);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}/open")) {
                return Http::response([
                    'id' => $raffleId,
                    'name' => 'Spring Raffle',
                    'status' => 'Open',
                    'ticketsSold' => 0,
                    'ticketPriceSats' => 21000,
                    'maxTickets' => 100,
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $create = $this->postJson("/api/stores/{$store->id}/raffles", [
            'name' => 'Spring Raffle',
            'ticketPriceSats' => 21000,
            'maxTickets' => 100,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.status', 'Draft');
        $create->assertJsonPath('data.allowedActions', ['open']);

        $open = $this->postJson("/api/stores/{$store->id}/raffles/{$raffleId}/open");

        $open->assertOk();
        $open->assertJsonPath('data.status', 'Open');
        $open->assertJsonPath('data.allowedActions', ['close']);
        $open->assertJsonPath('data.showsPublicLink', true);
    }

    public function test_create_raffle_with_fiat_currency_proxies_to_btcpay(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = 'raffle-eur-1';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle")) {
                $body = $request->data();
                $this->assertSame('EUR', $body['ticketCurrency'] ?? null);
                $this->assertEquals(5, $body['ticketPrice'] ?? null);
                $this->assertArrayNotHasKey('ticketPriceSats', $body);

                return Http::response([
                    'id' => $raffleId,
                    'name' => 'Euro Raffle',
                    'ticketCurrency' => 'EUR',
                    'ticketPrice' => 5.0,
                    'ticketPriceSats' => null,
                    'status' => 'Draft',
                    'ticketsSold' => 0,
                    'maxTickets' => null,
                ], 201);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/stores/{$store->id}/raffles", [
            'name' => 'Euro Raffle',
            'ticketCurrency' => 'eur',
            'ticketPrice' => 5,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.ticketCurrency', 'EUR');
    }

    public function test_update_draft_raffle_proxies_put(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = '11111111-1111-1111-1111-111111111111';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'PUT' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}")) {
                $body = $request->data();
                $this->assertSame('Updated name', $body['name'] ?? null);
                $this->assertSame(25000, $body['ticketPriceSats'] ?? null);

                return Http::response([
                    'id' => $raffleId,
                    'name' => 'Updated name',
                    'status' => 'Draft',
                    'ticketPriceSats' => 25000,
                    'maxTickets' => 50,
                    'ticketsSold' => 0,
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/stores/{$store->id}/raffles/{$raffleId}", [
            'name' => 'Updated name',
            'ticketPriceSats' => 25000,
            'maxTickets' => 50,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated name');
        $response->assertJsonPath('data.status', 'Draft');
    }

    public function test_delete_draft_raffle_proxies_delete(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = '44444444-4444-4444-4444-444444444444';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}")) {
                return Http::response(['deleted' => true], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/stores/{$store->id}/raffles/{$raffleId}");

        $response->assertOk();
        $response->assertJsonPath('data.deleted', true);
    }

    public function test_tickets_list_masks_buyer_email(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = '55555555-5555-5555-5555-555555555555';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'GET' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}/tickets")) {
                return Http::response([
                    [
                        'ticketNumber' => 1,
                        'buyerName' => 'Alice',
                        'buyerEmail' => 'alice@secret.com',
                        'allocatedAt' => '2026-05-18T12:00:00Z',
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/raffles/{$raffleId}/tickets");

        $response->assertOk();
        $response->assertJsonPath('data.0.buyerEmail', 'a***@secret.com');
        $response->assertJsonPath('data.0.buyerName', 'Alice');
    }

    public function test_presenter_token_proxies_post(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        config([
            'services.btcpay.base_url' => 'http://btcpay-internal:49392',
            'services.btcpay.public_url' => 'https://btcpay.example.com',
        ]);
        $apiBase = rtrim(config('services.btcpay.base_url'), '/');
        $publicBase = rtrim(config('services.btcpay.public_url'), '/');
        $raffleId = '22222222-2222-2222-2222-222222222222';
        $internalPresenterUrl = 'http://btcpay:49392/raffle/'.$raffleId.'/present?token=abc';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($apiBase, $btcpayStoreId, $raffleId, $internalPresenterUrl) {
            $url = (string) $request->url();
            if (! str_contains($url, $apiBase)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}/presenter-token")) {
                return Http::response([
                    'token' => 'abc',
                    'expiresAt' => '2026-05-18T18:00:00Z',
                    'presenterUrl' => $internalPresenterUrl,
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/stores/{$store->id}/raffles/{$raffleId}/presenter-token");

        $expectedPublicUrl = $publicBase.'/raffle/'.$raffleId.'/present?token=abc';

        $response->assertOk();
        $response->assertJsonPath('data.presenterUrl', $expectedPublicUrl);
        $response->assertJsonPath('data.token', 'abc');
    }

    public function test_presenter_token_rebuilds_relative_url(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        config(['services.btcpay.public_url' => 'https://pay.example.com']);
        $apiBase = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $publicBase = rtrim(config('services.btcpay.public_url'), '/');
        $raffleId = '33333333-3333-3333-3333-333333333333';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($apiBase, $btcpayStoreId, $raffleId) {
            $url = (string) $request->url();
            if (! str_contains($url, $apiBase)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}/presenter-token")) {
                return Http::response([
                    'token' => 'xyz',
                    'expiresAt' => '2026-05-18T18:00:00Z',
                    'presenterUrl' => '/raffle/'.$raffleId.'/present?token=xyz',
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/stores/{$store->id}/raffles/{$raffleId}/presenter-token");

        $response->assertOk();
        $response->assertJsonPath('data.presenterUrl', $publicBase.'/raffle/'.$raffleId.'/present?token=xyz');
    }
}
