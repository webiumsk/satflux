<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Invoicing\BusinessExpenseListFilters;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseListFiltersTest extends TestCase
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
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro', 'price_eur' => 99,
            'billing_period' => 'year', 'max_stores' => 3, 'max_api_keys' => 3,
            'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create(['user_id' => $this->user->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addYear()]);
        $this->company = Company::create(['user_id' => $this->user->id, 'legal_name' => 'Synthetic Filter Company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
    }

    #[Test]
    public function search_and_ranges_cover_all_pages_and_the_same_summary_and_bulk_targets(): void
    {
        for ($i = 0; $i < 28; $i++) {
            $this->expense(['title' => 'ŽIAROVKA 10%_!', 'total' => '10.00', 'due_date' => '2026-06-15']);
        }
        $outside = $this->expense(['title' => 'ŽIAROVKA 10%_!', 'total' => '9.99', 'due_date' => '2026-06-15']);
        $this->expense(['title' => 'ŽIAROVKA 10xxx', 'total' => '10.00', 'due_date' => '2026-06-15']);
        $this->expense(['title' => 'ŽIAROVKA 10%_!', 'total' => '10.00', 'due_date' => '2026-06-16']);
        $this->expense(['title' => 'ŽIAROVKA 10%_!', 'status' => BusinessExpenseStatus::Cancelled]);
        $filters = ['filter' => 'unpaid', 'issue_from' => '2026-06-01', 'issue_to' => '2026-06-01',
            'due' => 'custom', 'due_from' => '2026-06-15', 'due_to' => '2026-06-15',
            'amount_min' => '10.00', 'amount_max' => '10', 'search' => 'žiarovka 10%_!'];
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($filters))->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonPath('total', 28)->assertJsonPath('summary', [
                'count' => 28, 'currencies' => [['currency' => 'EUR', 'count' => 28, 'total' => '280.00', 'unpaid_total' => '280.00']],
            ]);
        $this->getJson($url.'?'.http_build_query([...$filters, 'page' => 2]))->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('total', 28)->assertJsonPath('summary.count', 28);
        $this->postJson($url.'/bulk', [...$filters, 'action' => 'mark_paid', 'select_all' => true])
            ->assertOk()->assertJsonPath('data.processed', 28);
        $this->assertSame(BusinessExpenseStatus::Recorded, $outside->fresh()->status);
        $this->assertSame(28, BusinessExpense::where('company_id', $this->company->id)->where('status', 'paid')->count());
    }

    #[Test]
    public function literal_search_is_company_scoped_and_does_not_expand_percent_underscore_or_escape_character(): void
    {
        $literal = $this->expense(['internal_note' => 'A%_!B']);
        $this->expense(['internal_note' => 'AxxB']);
        $other = Company::create(['user_id' => $this->user->id, 'legal_name' => 'Other synthetic company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        $foreign = $this->expense(['company_id' => $other->id, 'internal_note' => 'A%_!B']);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query(['search' => 'a%_!b']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->id);
        $this->postJson($url.'/bulk', ['action' => 'mark_paid', 'select_all' => true, 'search' => 'a%_!b'])
            ->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSame(BusinessExpenseStatus::Recorded, $foreign->fresh()->status);
        $this->actingAs(User::factory()->create())->getJson($url.'?search=a')->assertForbidden();
    }

    #[Test]
    public function explicit_issue_bounds_override_legacy_year_and_due_filters_do_not_infer_payment_from_notes(): void
    {
        $older = $this->expense(['issue_date' => '2025-12-31', 'due_date' => '2025-12-31', 'internal_note' => 'Paid 9.00']);
        $newer = $this->expense(['issue_date' => '2026-06-01', 'due_date' => '2026-06-16']);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query(['year' => 2026]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $newer->id);
        $this->getJson($url.'?'.http_build_query(['year' => 2026, 'issue_to' => '2025-12-31', 'due' => 'overdue']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('summary.currencies.0.unpaid_total', '10.00');
    }

    /** @return array<string, array{array<string, string>}> */
    public static function overdueSelectors(): array
    {
        return [
            'overdue status' => [['filter' => 'overdue']],
            'overdue due date' => [['due' => 'overdue']],
            'both overdue selectors' => [['filter' => 'overdue', 'due' => 'overdue']],
        ];
    }

    #[Test]
    #[DataProvider('overdueSelectors')]
    public function captured_overdue_cutoff_keeps_list_and_bulk_targets_the_same_after_midnight(array $selector): void
    {
        $overdue = $this->expense(['due_date' => '2026-06-14']);
        $nextDay = $this->expense(['due_date' => '2026-06-15']);
        $noDueDate = $this->expense();
        $this->expense(['due_date' => '2026-06-14', 'status' => BusinessExpenseStatus::Paid]);
        $this->expense(['due_date' => '2026-06-14', 'status' => BusinessExpenseStatus::Cancelled]);
        $filters = [...$selector, 'overdue_before' => '2026-06-15'];
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('summary.currencies.0.unpaid_total', '10.00');

        $this->travelTo(now()->setDate(2026, 6, 16)->setTime(0, 1));
        $this->getJson($url.'?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue->id);
        $this->postJson($url.'/bulk', [...$filters, 'action' => 'mark_paid', 'select_all' => true])
            ->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSame(BusinessExpenseStatus::Paid, $overdue->fresh()->status);
        $this->assertSame(BusinessExpenseStatus::Recorded, $nextDay->fresh()->status);
        $this->assertSame(BusinessExpenseStatus::Recorded, $noDueDate->fresh()->status);
    }

    #[Test]
    #[DataProvider('overdueSelectors')]
    public function callers_without_an_overdue_cutoff_keep_the_existing_current_day_behavior(array $selector): void
    {
        $old = $this->expense(['due_date' => '2026-06-14']);
        $boundary = $this->expense(['due_date' => '2026-06-15']);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($selector))->assertOk()->assertJsonPath('total', 1);
        $this->travelTo(now()->setDate(2026, 6, 16)->setTime(0, 1));
        $this->getJson($url.'?'.http_build_query($selector))->assertOk()->assertJsonPath('total', 2);
        $this->getJson($url.'?'.http_build_query([...$selector, 'overdue_before' => null]))->assertOk()->assertJsonPath('total', 2);
        $this->postJson($url.'/bulk', [...$selector, 'action' => 'mark_paid', 'select_all' => true])
            ->assertOk()->assertJsonPath('data.processed', 2);
        $this->assertSame(BusinessExpenseStatus::Paid, $old->fresh()->status);
        $this->assertSame(BusinessExpenseStatus::Paid, $boundary->fresh()->status);
    }

    #[Test]
    public function overdue_cutoff_preserves_exact_ids_company_isolation_and_custom_due_intersections(): void
    {
        $selected = $this->expense(['due_date' => '2026-06-14']);
        $unselected = $this->expense(['due_date' => '2026-06-13']);
        $boundary = $this->expense(['due_date' => '2026-06-15']);
        $other = Company::create(['user_id' => $this->user->id, 'legal_name' => 'Other cutoff company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        $foreign = $this->expense(['company_id' => $other->id, 'due_date' => '2026-06-14']);
        $this->travelTo(now()->setDate(2026, 6, 16)->setTime(0, 1));
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $filters = ['filter' => 'overdue', 'overdue_before' => '2026-06-15', 'due' => 'custom',
            'due_from' => '2026-06-14', 'due_to' => '2026-06-15'];
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $selected->id);
        $this->postJson($url.'/bulk', [...$filters, 'action' => 'mark_paid',
            'expense_ids' => [$selected->id, $boundary->id, $foreign->id]])
            ->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSame(BusinessExpenseStatus::Paid, $selected->fresh()->status);
        foreach ([$unselected, $boundary, $foreign] as $expense) {
            $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        }

        $this->getJson($url.'?'.http_build_query(['filter' => 'paid', 'due' => 'overdue', 'overdue_before' => '2026-06-15']))
            ->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?'.http_build_query(['filter' => 'unpaid', 'due' => 'custom', 'due_from' => '2026-06-15',
            'due_to' => '2026-06-15', 'overdue_before' => '2026-06-15']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $boundary->id);
    }

    #[Test]
    public function overdue_cutoff_accepts_the_first_supported_calendar_year(): void
    {
        $this->expense(['due_date' => '2026-06-14']);
        $filters = ['filter' => 'overdue', 'overdue_before' => '0001-01-01'];
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($filters))->assertOk()->assertJsonPath('total', 0);
        $this->postJson($url.'/bulk', [...$filters, 'action' => 'mark_paid', 'select_all' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['expense_ids'])
            ->assertJsonMissingValidationErrors(['overdue_before']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidFilters(): iterable
    {
        yield 'year zero' => [['issue_from' => '0000-01-01']];
        yield 'bad date' => [['issue_from' => '2026-02-30']];
        yield 'overdue cutoff year zero' => [['filter' => 'overdue', 'overdue_before' => '0000-01-01']];
        yield 'invalid overdue cutoff calendar day' => [['due' => 'overdue', 'overdue_before' => '2026-02-30']];
        yield 'overdue cutoff is a date without time' => [['overdue_before' => '2026-06-15T12:00:00']];
        yield 'overdue cutoff requires canonical padding' => [['overdue_before' => '2026-6-15']];
        yield 'overdue cutoff is not an array' => [['overdue_before' => ['2026-06-15']]];
        yield 'overdue cutoff rejects SQL text' => [['overdue_before' => "2026-06-15' OR 1=1 --"]];
        yield 'reversed issue' => [['issue_from' => '2026-06-02', 'issue_to' => '2026-06-01']];
        yield 'reversed due' => [['due' => 'custom', 'due_from' => '2026-06-02', 'due_to' => '2026-06-01']];
        yield 'empty custom due' => [['due' => 'custom']];
        yield 'negative amount' => [['amount_min' => '-0.01']];
        yield 'beyond safe cents' => [['amount_max' => '90071992547409.92']];
        yield 'overflow exponent' => [['amount_max' => '1e309']];
        yield 'infinity' => [['amount_max' => 'Infinity']];
        yield 'precision' => [['amount_min' => '0.001']];
        yield 'reversed amount' => [['amount_min' => '10.01', 'amount_max' => '10.00']];
        yield 'exact large reversed amount' => [['amount_min' => '9007199254740992.01', 'amount_max' => '9007199254740992.00']];
        yield 'NUL search' => [['search' => "bad\0term"]];
        yield 'array search' => [['search' => ['private']]];
        yield 'long search' => [['search' => str_repeat('x', 201)]];
    }

    #[Test]
    #[DataProvider('invalidFilters')]
    public function listing_and_bulk_share_strict_filter_validation(array $filters): void
    {
        $expense = $this->expense();
        $url = "/api/invoicing/companies/{$this->company->id}/expenses";
        $this->actingAs($this->user)->getJson($url.'?'.http_build_query($filters))->assertUnprocessable();
        $this->postJson($url.'/bulk', [...$filters, 'action' => 'mark_paid', 'select_all' => true])->assertUnprocessable();
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    public function sqlite_unicode_predicate_registers_on_distinct_read_and_write_handles_and_reuses_active_cursors(): void
    {
        $write = new \PDO('sqlite::memory:');
        $read = new \PDO('sqlite::memory:');
        $connection = new SQLiteConnection($write, ':memory:', '', ['driver' => 'sqlite']);
        $connection->setReadPdo($read);
        $this->assertSame('satflux_expense_search_lower', BusinessExpenseListFilters::lowerFunction($connection));
        $cursor = $read->query("SELECT satflux_expense_search_lower('ŽIAROVKA') UNION ALL SELECT 'other'");
        $this->assertSame('žiarovka', $cursor->fetchColumn());
        BusinessExpenseListFilters::lowerFunction($connection);
        $this->assertSame('ťava', $connection->selectOne("SELECT satflux_expense_search_lower('ŤAVA') AS value")->value);
        $this->assertSame('ľalia', $write->query("SELECT satflux_expense_search_lower('ĽALIA')")->fetchColumn());
        $this->assertNull($read->query('SELECT satflux_expense_search_lower(NULL)')->fetchColumn());
        $cursor->closeCursor();
    }

    /** @param array<string, mixed> $data */
    private function expense(array $data = []): BusinessExpense
    {
        return BusinessExpense::create([...[
            'company_id' => $this->company->id, 'internal_number' => 'FILTER-'.++$this->sequence,
            'status' => BusinessExpenseStatus::Recorded, 'issue_date' => '2026-06-01', 'total' => '10.00', 'currency' => 'EUR',
        ], ...$data]);
    }
}
