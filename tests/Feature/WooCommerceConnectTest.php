<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WooCommerceConnectTest extends TestCase
{
    use RefreshDatabase;

    private const RETURN_URL = 'https://shop.example/wp-admin/admin.php?page=satflux';

    public function test_connect_with_single_store_asks_for_confirmation_instead_of_redirecting(): void
    {
        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get('/woocommerce/connect?return_url='.urlencode(self::RETURN_URL));

        $response->assertOk();
        $response->assertViewIs('woocommerce.connect');
        $response->assertSee('shop.example');
        $this->assertSame(0, StoreIntegration::count());
    }

    public function test_connect_with_preselected_store_still_requires_confirmation(): void
    {
        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id]);
        $second = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get('/woocommerce/connect?store_id='.$second->id.'&return_url='.urlencode(self::RETURN_URL));

        $response->assertOk();
        $response->assertViewHas('selectedStoreId', $second->id);
        $this->assertSame(0, StoreIntegration::count());
    }

    public function test_confirmed_post_redirects_with_one_time_code(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/woocommerce/connect/select-store', [
            'return_url' => self::RETURN_URL,
            'store_id' => $store->id,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::RETURN_URL.'&satflux_return=1&satflux_connect_code=', $location);
        $this->assertSame(1, StoreIntegration::where('store_id', $store->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'store_integration.woocommerce_connected',
            'target_id' => $store->id,
        ]);
    }

    public function test_confirmed_post_rejects_foreign_store(): void
    {
        $user = User::factory()->create();
        $foreign = Store::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->post('/woocommerce/connect/select-store', [
            'return_url' => self::RETURN_URL,
            'store_id' => $foreign->id,
        ])->assertForbidden();

        $this->assertSame(0, StoreIntegration::count());
    }

    public function test_connect_rejects_non_https_return_url(): void
    {
        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/woocommerce/connect?return_url='.urlencode('http://shop.example/'))
            ->assertSessionHasErrors('return_url');
    }
}
