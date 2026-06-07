<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseBulkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

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
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Bulk Expense Co',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function bulk_mark_paid_by_ids(): void
    {
        $recorded = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => '20260001',
            'issue_date' => '2026-06-01',
            'total' => 10,
            'currency' => 'EUR',
        ]);
        $paid = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Paid,
            'internal_number' => '20260002',
            'issue_date' => '2026-06-01',
            'total' => 5,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/expenses/bulk", [
                'action' => 'mark_paid',
                'expense_ids' => [$recorded->id, $paid->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertEquals(BusinessExpenseStatus::Paid, $recorded->fresh()->status);
    }
}
