<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedStatsAndAutoExportTest extends TestCase
{
    use RefreshDatabase;

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
            'features' => ['advanced_statistics', 'automatic_csv_exports'],
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
    }

    /** @test */
    public function advanced_stats_endpoint_returns_403_for_free_user(): void
    {
        $response = $this->actingAs($this->freeUser)->getJson('/api/stats/advanced');

        $response->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Pro') || str_contains($m, 'Advanced'));
    }

    /** @test */
    public function advanced_stats_endpoint_returns_200_for_pro_user(): void
    {
        $response = $this->actingAs($this->proUser)->getJson('/api/stats/advanced');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['stores', 'overall']]);
    }

    /** @test */
    public function support_user_can_view_basic_stats_for_another_users_store(): void
    {
        $owner = User::factory()->create(['btcpay_api_key' => null]);
        $support = User::factory()->support()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($support)->getJson("/api/stores/{$store->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'invoice_count_30d',
                    'invoice_count_all_time',
                    'paid_amount_30d',
                    'paid_amount_all_time',
                    'currency',
                ],
            ]);
    }

    /** @test */
    public function non_support_user_cannot_view_basic_stats_for_another_users_store(): void
    {
        $owner = User::factory()->create(['btcpay_api_key' => null]);
        $otherUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->getJson("/api/stores/{$store->id}/stats");

        $response->assertStatus(403);
    }
}
