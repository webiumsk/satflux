<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessInvoicingGatingTest extends TestCase
{
    use RefreshDatabase;

    protected User $freeUser;

    protected User $proUser;

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
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);

        $this->freeUser = User::factory()->create();
        $this->proUser = User::factory()->create(['role' => 'pro']);
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

    #[Test]
    public function free_user_cannot_list_companies(): void
    {
        $this->actingAs($this->freeUser)
            ->getJson('/api/invoicing/companies')
            ->assertForbidden();
    }

    #[Test]
    public function pro_user_can_list_companies(): void
    {
        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/companies')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function pro_role_user_can_use_invoicing_even_without_feature_flag_on_plan(): void
    {
        $legacyProPlan = SubscriptionPlan::create([
            'code' => 'legacy_pro',
            'name' => 'legacy_pro',
            'display_name' => 'Legacy Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['automatic_csv_exports'],
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'pro']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $legacyProPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/invoicing/companies')
            ->assertOk();
    }
}
