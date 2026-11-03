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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0));
        $plan = SubscriptionPlan::create([
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
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Synthetic Summary Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function summary_covers_all_filtered_pages_and_separates_currencies(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->expense(['total' => '0.10']);
        }
        $this->expense(['status' => BusinessExpenseStatus::Paid, 'total' => '0.20']);
        $this->expense(['currency' => 'CZK', 'total' => '25.50']);
        $this->expense(['currency' => 'CZK', 'status' => BusinessExpenseStatus::Paid, 'total' => '10.25']);
        $this->expense(['status' => BusinessExpenseStatus::Cancelled, 'total' => '999.00']);
        $deleted = $this->expense(['total' => '888.00']);
        $deleted->delete();

        $otherCompany = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Synthetic Other Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $this->expense(['company_id' => $otherCompany->id, 'total' => '777.00']);

        $expected = ['count' => 29, 'currencies' => [
            ['currency' => 'CZK', 'count' => 2, 'total' => '35.75', 'unpaid_total' => '25.50'],
            ['currency' => 'EUR', 'count' => 27, 'total' => '2.80', 'unpaid_total' => '2.60'],
        ]];
        $first = $this->listing(['per_page' => 25]);
        $first->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('total', 29)
            ->assertJsonPath('summary', $expected);
        $second = $this->listing(['per_page' => 25, 'page' => 2]);
        $second->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('summary', $expected)
            ->assertJsonPath('current_page', 2)->assertJsonPath('last_page', 2);
    }

    #[Test]
    public function summary_uses_the_same_status_year_and_date_range_filters_as_the_list(): void
    {
        $this->expense(['total' => '10.10', 'due_date' => '2026-06-01']);
        $this->expense(['total' => '20.20', 'due_date' => '2026-06-16']);
        $this->expense(['total' => '30.30', 'issue_date' => '2026-05-31', 'due_date' => null]);
        $this->expense(['total' => '40.40', 'status' => BusinessExpenseStatus::Paid]);
        $this->expense(['total' => '50.50', 'issue_date' => '2025-06-01', 'due_date' => '2025-06-02']);
        $this->expense(['total' => '999.00', 'status' => BusinessExpenseStatus::Cancelled]);

        foreach ([
            [['year' => 2026], 4, '101.00', '60.60'],
            [['year' => 2026, 'filter' => 'paid'], 1, '40.40', '0.00'],
            [['year' => 2026, 'filter' => 'unpaid'], 3, '60.60', '60.60'],
            [['year' => 2026, 'filter' => 'overdue'], 1, '10.10', '10.10'],
            [['year' => 2025], 1, '50.50', '50.50'],
            [['issue_from' => '2026-06-01', 'issue_to' => '2026-06-30'], 3, '70.70', '30.30'],
            [['issue_to' => '2026-05-31', 'year' => 2026], 2, '80.80', '80.80'],
        ] as [$filters, $count, $total, $unpaid]) {
            $this->listing($filters)->assertOk()->assertJsonPath('total', $count)
                ->assertJsonPath('summary', ['count' => $count, 'currencies' => [
                    ['currency' => 'EUR', 'count' => $count, 'total' => $total, 'unpaid_total' => $unpaid],
                ]]);
        }
    }

    #[Test]
    public function summary_uses_recorded_status_without_inferring_payments_from_notes_or_dates(): void
    {
        $this->expense([
            'total' => '100.00',
            'paid_at' => now()->subDay(),
            'internal_note' => "Payment amount: 99.00\nPayment currency: EUR\nPayment date: 2026-06-14",
        ]);
        $this->expense(['total' => '40.00', 'status' => BusinessExpenseStatus::Paid, 'paid_at' => null]);

        $this->listing()->assertOk()->assertJsonPath('summary', ['count' => 2, 'currencies' => [
            ['currency' => 'EUR', 'count' => 2, 'total' => '140.00', 'unpaid_total' => '100.00'],
        ]]);
    }

    #[Test]
    public function summary_preserves_exact_cents_and_large_aggregate_totals(): void
    {
        $this->expense(['total' => '0.10']);
        $this->expense(['total' => '0.20']);
        for ($i = 0; $i < 26; $i++) {
            $this->expense(['currency' => 'CZK', 'total' => '999999999999.99']);
        }

        $this->listing()->assertOk()->assertJsonPath('summary', ['count' => 28, 'currencies' => [
            ['currency' => 'CZK', 'count' => 26, 'total' => '25999999999999.74', 'unpaid_total' => '25999999999999.74'],
            ['currency' => 'EUR', 'count' => 2, 'total' => '0.30', 'unpaid_total' => '0.30'],
        ]]);
    }

    #[Test]
    public function summary_preserves_negative_cents_and_excludes_cancelled_adjustments(): void
    {
        $this->expense(['total' => '0.10']);
        $this->expense(['total' => '-0.20']);
        $this->expense(['status' => BusinessExpenseStatus::Paid, 'total' => '0.05']);
        $this->expense(['status' => BusinessExpenseStatus::Cancelled, 'total' => '-999.00']);

        $this->listing()->assertOk()->assertJsonPath('total', 3)
            ->assertJsonPath('summary', ['count' => 3, 'currencies' => [
                ['currency' => 'EUR', 'count' => 3, 'total' => '-0.05', 'unpaid_total' => '-0.10'],
            ]]);
    }

    #[Test]
    public function summary_normalizes_currency_case_and_defaults_blank_currency_to_eur(): void
    {
        $this->expense(['currency' => 'eur', 'total' => '0.10']);
        $this->expense(['currency' => '', 'total' => '0.20']);
        $this->expense(['currency' => 'czk', 'total' => '1.00']);

        $this->listing()->assertOk()->assertJsonPath('summary', ['count' => 3, 'currencies' => [
            ['currency' => 'CZK', 'count' => 1, 'total' => '1.00', 'unpaid_total' => '1.00'],
            ['currency' => 'EUR', 'count' => 2, 'total' => '0.30', 'unpaid_total' => '0.30'],
        ]]);
    }

    #[Test]
    public function empty_filtered_list_has_an_empty_summary(): void
    {
        $this->expense(['issue_date' => '2025-01-01']);
        $this->listing(['year' => 2026])->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('total', 0)->assertJsonPath('summary', ['count' => 0, 'currencies' => []]);
    }

    #[Test]
    public function summaries_remain_company_owned_and_require_authentication(): void
    {
        $this->expense(['total' => '123.45']);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function expense(array $data = []): BusinessExpense
    {
        return BusinessExpense::create(array_merge([
            'company_id' => $this->company->id,
            'internal_number' => 'SUMMARY-'.++$this->sequence,
            'status' => BusinessExpenseStatus::Recorded,
            'issue_date' => '2026-06-01',
            'total' => '1.00',
            'currency' => 'EUR',
        ], $data));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function listing(array $filters = []): TestResponse
    {
        return $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/expenses?".http_build_query($filters),
        );
    }
}
