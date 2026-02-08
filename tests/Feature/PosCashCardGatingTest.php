<?php

namespace Tests\Feature;

use App\Models\PosTerminal;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashCardGatingTest extends TestCase
{
    use RefreshDatabase;

    protected User $freeUser;
    protected User $proUser;
    protected Store $store;
    protected PosTerminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $freePlan = SubscriptionPlan::create([
            'code' => 'free',
            'name' => 'free',
            'display_name' => 'Free',
            'price_eur' => 0,
            'billing_period' => 'year',
            'max_stores' => 1,
            'max_api_keys' => 1,
            'max_ln_addresses' => 1,
            'features' => [],
            'is_active' => true,
        ]);
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['offline_payment_methods'],
            'is_active' => true,
        ]);

        $this->freeUser = User::factory()->create();
        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->freeUser->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $this->terminal = PosTerminal::create([
            'store_id' => $this->store->id,
            'name' => 'Counter',
            'settings_json' => ['enabled_payment_methods' => ['lightning', 'onchain', 'cash', 'card']],
        ]);
    }

    /** @test */
    public function free_user_cannot_create_pos_order_with_paid_method_cash(): void
    {
        $store = Store::factory()->create(['user_id' => $this->freeUser->id]);
        $terminal = PosTerminal::create([
            'store_id' => $store->id,
            'name' => 'Counter',
            'settings_json' => ['enabled_payment_methods' => ['lightning', 'onchain']],
        ]);

        $response = $this->actingAs($this->freeUser)->postJson("/api/stores/{$store->id}/pos-orders", [
            'pos_terminal_id' => $terminal->id,
            'amount' => 10,
            'currency' => 'EUR',
            'paid_method' => 'cash',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Pro'));
    }

    /** @test */
    public function pro_user_can_create_pos_order_with_paid_method_cash(): void
    {
        $response = $this->actingAs($this->proUser)->postJson("/api/stores/{$this->store->id}/pos-orders", [
            'pos_terminal_id' => $this->terminal->id,
            'amount' => 25.50,
            'currency' => 'EUR',
            'paid_method' => 'cash',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.paid_method', 'cash')
            ->assertJsonPath('data.status', 'paid');
    }
}
