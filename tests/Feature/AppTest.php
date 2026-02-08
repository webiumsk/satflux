<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock BTCPay API responses
        $baseUrl = config('services.btcpay.base_url', 'http://localhost');
        
        Http::fake([
            // Store creation
            $baseUrl . '/api/v1/stores' => Http::response([
                'id' => 'test-store-id',
                'name' => 'Test Store',
            ], 201),
            
            // App creation - use wildcard pattern to match any store ID
            $baseUrl . '/api/v1/stores/*/apps/pos' => Http::response([], 201, ['Location' => $baseUrl . '/api/v1/apps/pos/test-app-id']),
            
            // App get/update
            $baseUrl . '/api/v1/apps/pos/*' => Http::response([
                'id' => 'test-app-id',
                'appName' => 'Test PoS',
                'appType' => 'PointOfSale',
                'storeId' => 'test-store-id',
                'config' => [
                    'title' => 'Test PoS',
                    'defaultView' => 'Cart',
                    'currency' => 'EUR',
                ],
            ], 200),
            
            // App delete
            $baseUrl . '/api/v1/apps/*' => Http::response([], 204),
            
            // Store get/update
            $baseUrl . '/api/v1/stores/*' => Http::response([
                'id' => 'test-store-id',
                'name' => 'Test Store',
                'defaultCurrency' => 'EUR',
                'timeZone' => 'Europe/Vienna',
                'preferredExchange' => 'kraken',
            ], 200),
        ]);
    }

    public function test_user_can_create_pos_app(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'default_currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/apps", [
            'app_type' => 'PointOfSale',
            'name' => 'My PoS',
            'config' => [
                'title' => 'My Point of Sale',
                'defaultView' => 'Cart',
                'currency' => 'EUR',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'app_type'],
        ]);
        
        $this->assertDatabaseHas('apps', [
            'store_id' => $store->id,
            'app_type' => 'PointOfSale',
        ]);
    }

    public function test_app_creation_uses_store_default_currency(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'default_currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/apps", [
            'app_type' => 'PointOfSale',
            'name' => 'My PoS',
            'config' => [
                'title' => 'My Point of Sale',
                'defaultView' => 'Cart',
                // No currency specified - should use store default
            ],
        ]);

        $response->assertStatus(201);
        
        // The app should use store's default currency
        // Verify the request was sent with the correct currency
        // AppService flattens config into request body directly (not nested under 'config')
        Http::assertSent(function ($request) use ($store) {
            $url = (string) $request->url();
            // Check if this is a POST request to the app creation endpoint
            if ($request->method() !== 'POST' || !str_contains($url, '/api/v1/stores/') || !str_contains($url, '/apps/pos')) {
                return false;
            }
            
            $body = $request->data();
            // AppService creates request body by flattening config fields directly
            // So currency is at root level: { appName: "...", title: "...", defaultView: "...", currency: "USD" }
            $currency = $body['currency'] ?? null;
            return $currency === $store->default_currency;
        });
    }

    public function test_user_can_update_app_settings(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $app = App::factory()->create([
            'store_id' => $store->id,
            'app_type' => 'PointOfSale',
            'btcpay_app_id' => 'test-app-id',
        ]);

        $response = $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", [
            'name' => 'Updated PoS',
            'config' => [
                'title' => 'Updated Title',
                'defaultView' => 'Light',
                'currency' => 'BTC',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('apps', [
            'id' => $app->id,
            'name' => 'Updated PoS',
        ]);
    }

    public function test_user_can_delete_app(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $app = App::factory()->create([
            'store_id' => $store->id,
            'app_type' => 'PointOfSale',
            'btcpay_app_id' => 'test-app-id',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/stores/{$store->id}/apps/{$app->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('apps', ['id' => $app->id]);
    }

    public function test_user_cannot_access_other_users_apps(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user1->id]);
        $app = App::factory()->create([
            'store_id' => $store->id,
            'app_type' => 'PointOfSale',
        ]);

        $response = $this->actingAs($user2)->getJson("/api/stores/{$store->id}/apps/{$app->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_list_store_apps(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        App::factory()->count(3)->create([
            'store_id' => $store->id,
            'app_type' => 'PointOfSale',
        ]);

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }
}

