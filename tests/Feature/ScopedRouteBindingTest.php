<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\App;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nested route parameters are scoped to their route parent at binding time -
 * a child under the wrong parent 404s even when the caller owns both parents
 * (so ownership middleware alone would not catch it).
 */
class ScopedRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function makeCompany(string $name): Company
    {
        return Company::create([
            'user_id' => $this->user->id,
            'legal_name' => $name,
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    protected function makeExpense(Company $company): BusinessExpense
    {
        return BusinessExpense::create([
            'company_id' => $company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260001',
            'issue_date' => now(),
            'total' => 50,
            'currency' => 'EUR',
        ]);
    }

    #[Test]
    public function app_under_foreign_store_of_same_owner_is_404(): void
    {
        $storeA = Store::factory()->create(['user_id' => $this->user->id]);
        $storeB = Store::factory()->create(['user_id' => $this->user->id]);
        $app = App::create([
            'id' => (string) Str::uuid(),
            'store_id' => $storeB->id,
            'btcpay_app_id' => 'btcpay-app-1',
            'app_type' => 'PointOfSale',
            'name' => 'PoS',
            'config' => [],
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/stores/{$storeA->id}/apps/{$app->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function expense_under_foreign_company_of_same_owner_is_404(): void
    {
        $companyA = $this->makeCompany('Company A');
        $companyB = $this->makeCompany('Company B');
        $expense = $this->makeExpense($companyB);

        $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$companyA->id}/expenses/{$expense->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function expense_under_own_company_still_resolves(): void
    {
        $company = $this->makeCompany('Company A');
        $expense = $this->makeExpense($company);

        $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$company->id}/expenses/{$expense->id}")
            ->assertOk();
    }
}
