<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyLimitTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $proPlan;

    protected User $proUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['invoicing.beta_pro_max_companies' => null]);

        SubscriptionPlan::create([
            'code' => 'free',
            'name' => 'free',
            'display_name' => 'Free',
            'price_eur' => 0,
            'billing_period' => 'year',
            'max_stores' => 1,
            'max_api_keys' => 1,
            'max_ln_addresses' => 1,
            'max_companies' => 0,
            'features' => [],
            'is_active' => true,
        ]);

        $this->proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'max_companies' => 2,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);

        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    #[Test]
    public function pro_user_cannot_create_third_company(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Company::create([
                'user_id' => $this->proUser->id,
                'legal_name' => "Company {$i}",
                'jurisdiction' => 'eu_sk',
                'country' => 'SK',
            ]);
        }

        $this->actingAs($this->proUser)
            ->postJson('/api/invoicing/companies', [
                'legal_name' => 'Third s.r.o.',
                'jurisdiction' => 'eu_sk',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'company_limit')
            ->assertJsonPath('max_allowed', 2);
    }

    #[Test]
    public function beta_override_allows_five_companies_for_pro(): void
    {
        config(['invoicing.beta_pro_max_companies' => 5]);

        for ($i = 0; $i < 4; $i++) {
            Company::create([
                'user_id' => $this->proUser->id,
                'legal_name' => "Beta Co {$i}",
                'jurisdiction' => 'eu_sk',
                'country' => 'SK',
            ]);
        }

        $this->actingAs($this->proUser)
            ->postJson('/api/invoicing/companies', [
                'legal_name' => 'Fifth s.r.o.',
                'jurisdiction' => 'eu_sk',
            ])
            ->assertCreated();

        $this->actingAs($this->proUser)
            ->postJson('/api/invoicing/companies', [
                'legal_name' => 'Sixth s.r.o.',
                'jurisdiction' => 'eu_sk',
            ])
            ->assertForbidden()
            ->assertJsonPath('max_allowed', 5);
    }

    #[Test]
    public function expired_subscription_loses_invoicing_access(): void
    {
        $subscription = $this->proUser->currentSubscription();
        $subscription->update([
            'status' => 'expired',
            'expires_at' => now()->subMonth(),
            'grace_ends_at' => now()->subDays(1),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/companies')
            ->assertForbidden();
    }
}
