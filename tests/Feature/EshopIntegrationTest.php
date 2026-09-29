<?php

namespace Tests\Feature;

use App\Http\Controllers\EshopIntegrationController;
use App\Models\Store;
use App\Models\StoreApiKey;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BtcPay\BtcPayClient;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\Support\OutboundUrlGuardTest;

class EshopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $this->app->forgetInstance(BtcPayClient::class);
        SubscriptionPlan::create([
            'name' => 'free',
            'display_name' => 'Free',
            'price_eur' => 0,
            'max_stores' => 5,
            'max_api_keys' => 10,
            'features' => [],
            'is_active' => true,
        ]);
        Http::fake(fn () => Http::response([
            'id' => 'btcpay-key-'.uniqid(),
            'apiKey' => 'secret-'.bin2hex(random_bytes(8)),
        ], 201));
    }

    private function store(): Store
    {
        $user = User::factory()->create(['btcpay_user_id' => 'btcpay-user-1']);

        return Store::factory()->create(['user_id' => $user->id]);
    }

    public function test_connect_token_is_single_use(): void
    {
        $store = $this->store();
        $token = EshopIntegrationController::generateToken($store->id, [], 'Shop');

        $this->postJson('/api/public/eshop/connect', ['store_id' => $store->id, 'token' => $token])->assertOk();
        $this->postJson('/api/public/eshop/connect', ['store_id' => $store->id, 'token' => $token])->assertStatus(400);

        $this->assertSame(1, StoreApiKey::where('store_id', $store->id)->count());
    }

    public function test_token_exchange_never_returns_an_existing_key(): void
    {
        $store = $this->store();
        $existing = StoreApiKey::create([
            'store_id' => $store->id,
            'label' => 'E-shop Integration',
            'btcpay_api_key' => 'pre-existing-secret',
            'permissions' => ['btcpay.store.canviewinvoices'],
            'is_active' => true,
        ]);
        $token = EshopIntegrationController::generateToken($store->id);

        $response = $this->getJson("/api/public/eshop/token/{$token}")->assertOk();

        $this->assertNotSame($existing->btcpay_api_key, $response->json('data.api_key'));
        $this->assertSame(2, StoreApiKey::where('store_id', $store->id)->count());
        $this->getJson("/api/public/eshop/token/{$token}")->assertStatus(400);
    }

    public function test_a_claimed_token_cannot_be_claimed_again_even_if_still_cached(): void
    {
        $store = $this->store();
        $token = EshopIntegrationController::generateToken($store->id, [], 'Shop');
        $data = Cache::get("eshop_token:{$token}");

        $this->getJson("/api/public/eshop/token/{$token}")->assertOk();
        // A concurrent reader that fetched the payload before it was removed.
        Cache::put("eshop_token:{$token}", $data, now()->addMinutes(5));

        $this->postJson('/api/public/eshop/connect', ['store_id' => $store->id, 'token' => $token])->assertStatus(400);
        $this->assertSame(1, StoreApiKey::where('store_id', $store->id)->count());
    }

    public function test_connect_rejects_an_unsafe_callback_before_creating_a_key(): void
    {
        $store = $this->store();
        $token = EshopIntegrationController::generateToken($store->id, [], 'Shop');
        $this->app->instance(OutboundUrlGuard::class, OutboundUrlGuardTest::guardWithDns(['internal.example.com' => ['10.0.0.8']]));

        $this->postJson('/api/public/eshop/connect', [
            'store_id' => $store->id,
            'token' => $token,
            'callback_url' => 'https://internal.example.com/hook',
        ])->assertStatus(422)->assertJsonValidationErrors('callback_url');

        $this->assertSame(0, StoreApiKey::where('store_id', $store->id)->count());
    }
}
