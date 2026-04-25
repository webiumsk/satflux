<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebugStoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_debug_stores_response_does_not_expose_btcpay_store_ids(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $user = User::factory()->create(['btcpay_api_key' => null]);
        Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'sensitive-btcpay-store-id',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/debug/stores');

        $response->assertStatus(200);
        $response->assertJsonPath('local_stores.0.has_btcpay_store_id', true);
        $response->assertJsonMissing(['btcpay_store_id' => 'sensitive-btcpay-store-id']);
        $this->assertStringNotContainsString('sensitive-btcpay-store-id', $response->getContent());
    }
}
