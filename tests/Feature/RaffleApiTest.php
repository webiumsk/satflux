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

    public function test_presenter_token_proxies_post(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $raffleId = '22222222-2222-2222-2222-222222222222';
        $presenterUrl = 'https://btcpay.example.com/raffle/'.$raffleId.'/present?token=abc';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId, $presenterUrl) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'POST' && str_ends_with($url, "/api/v1/stores/{$btcpayStoreId}/raffle/{$raffleId}/presenter-token")) {
                return Http::response([
                    'token' => 'abc',
                    'expiresAt' => '2026-05-18T18:00:00Z',
                    'presenterUrl' => $presenterUrl,
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/stores/{$store->id}/raffles/{$raffleId}/presenter-token");

        $response->assertOk();
        $response->assertJsonPath('data.presenterUrl', $presenterUrl);
        $response->assertJsonPath('data.token', 'abc');
    }
}
