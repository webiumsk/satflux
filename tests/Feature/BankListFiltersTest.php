<?php

namespace Tests\Feature;

use App\Enums\BankImportSource;
use App\Enums\BankMatchType;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = SubscriptionPlan::create(['code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro', 'price_eur' => 99,
            'billing_period' => 'year', 'max_stores' => 3, 'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true]);
        $this->user = User::factory()->create();
        Subscription::create(['user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear()]);
        $this->company = Company::create(['user_id' => $this->user->id, 'legal_name' => 'Synthetic bank filters',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
    }

    /** @param array<string, mixed> $patch */
    private function movement(array $patch = []): BankTransaction
    {
        return BankTransaction::create(array_merge(['company_id' => $this->company->id, 'booked_at' => '2026-11-16 12:00:00',
            'amount' => '19.05', 'currency' => 'EUR', 'direction' => BankTransactionDirection::Debit,
            'match_status' => BankTransactionMatchStatus::Unmatched, 'counterparty_name' => 'Synthetic counterparty',
            'reference' => 'Synthetic movement', 'source' => BankImportSource::Csv, 'dedupe_hash' => bin2hex(random_bytes(32))], $patch));
    }

    /** @param array<string, mixed> $filters */
    private function listing(array $filters = []): TestResponse
    {
        return $this->actingAs($this->user)->getJson('/api/invoicing/companies/'.$this->company->id.'/bank-transactions?'.http_build_query($filters));
    }

    #[Test]
    public function search_finds_old_rows_beyond_both_display_and_scan_batch_boundaries(): void
    {
        for ($i = 0; $i < 260; $i++) {
            $this->movement(['booked_at' => '2026-11-17 12:00:00', 'reference' => 'Other movement '.$i]);
        }
        $older = $this->movement(['reference' => 'Retained needle', 'booked_at' => '2026-11-15 12:00:00']);
        $result = $this->listing(['search' => 'needle', 'per_page' => 50]);
        $result->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.summary.debit_total', '19.05');
    }

    #[Test]
    public function scan_uses_a_composite_cursor_across_timestamp_ties_without_sql_offsets(): void
    {
        $ids = [];
        for ($i = 0; $i < 505; $i++) {
            $ids[] = $this->movement(['booked_at' => $i < 502 ? '2026-11-17 12:00:00' : '2026-11-16 12:00:00'])->id;
        }
        $expected = array_merge(collect(array_slice($ids, 0, 502))->sortDesc()->values()->all(),
            collect(array_slice($ids, 502))->sortDesc()->values()->all());
        $scanQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$scanQueries): void {
            if (str_contains($query->sql, 'bank_transactions') && str_contains($query->sql, 'limit 250')) {
                $scanQueries[] = $query->sql;
            }
        });
        $result = $this->listing(['page' => 6, 'per_page' => 100]);
        $result->assertOk()->assertJsonPath('meta.total', 505)->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.summary.debit_count', 505)->assertJsonPath('meta.summary.debit_total', '9620.25');
        $this->assertSame(array_slice($expected, 500), array_column($result->json('data'), 'id'));
        $this->assertCount(3, $scanQueries);
        foreach ($scanQueries as $query) {
            $this->assertStringNotContainsString('offset', strtolower($query));
        }
        $this->assertStringContainsString('"booked_at" <', $scanQueries[1]);
        $this->assertStringContainsString('"id" <', $scanQueries[1]);
    }

    #[Test]
    public function direction_bounds_and_summary_use_resolved_direction_without_changing_payment_evidence(): void
    {
        $legacy = $this->movement(['direction' => BankTransactionDirection::Credit, 'amount' => '12.34', 'reference' => 'Debet na ucte synthetic']);
        $this->movement(['amount' => '9.99']);
        $this->movement(['direction' => BankTransactionDirection::Credit, 'currency' => 'USD', 'amount' => '3.00']);
        $this->movement(['amount' => '-55.00', 'reference' => 'Stav na ucte', 'booked_at' => '2026-11-18 12:00:00']);
        $response = $this->listing(['direction' => 'debit', 'amount_min' => '12.34', 'amount_max' => '12.34',
            'from' => '2026-11-16', 'to' => '2026-11-16']);
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $legacy->id)
            ->assertJsonPath('data.0.direction', 'debit')->assertJsonPath('data.0.recorded_direction', 'credit')
            ->assertJsonPath('meta.summary.credit_count', 0)->assertJsonPath('meta.summary.debit_count', 1)
            ->assertJsonPath('meta.summary.debit_total', '12.34')->assertJsonPath('meta.summary.balance', '-12.34')
            ->assertJsonPath('meta.summary.account_balance.amount', '-55.00');
        $this->listing()->assertOk()->assertJsonPath('meta.summary.currency', null)
            ->assertJsonPath('meta.summary.balance', null)->assertJsonCount(2, 'meta.summary.by_currency');
    }

    #[Test]
    public function booking_dates_include_the_whole_end_day_and_sort_ties_by_id(): void
    {
        $first = $this->movement(['booked_at' => '2026-11-16 23:59:59']);
        $second = $this->movement(['booked_at' => '2026-11-16 23:59:59']);
        $this->movement(['booked_at' => '2026-11-17 00:00:00']);
        $expected = collect([$first->id, $second->id])->sortDesc()->values()->all();
        $response = $this->listing(['from' => '2026-11-16', 'to' => '2026-11-16']);
        $response->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame($expected, array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function literal_unicode_and_account_search_do_not_treat_percent_underscore_or_slash_as_patterns(): void
    {
        $literal = $this->movement(['reference' => 'Literal %_\\ entry', 'counterparty_name' => 'ŽLTÝ TEST', 'counterparty_iban' => 'SYNTHETIC-ACCOUNT-019']);
        $this->movement(['reference' => 'Literal ANY entry']);
        foreach (['%_\\', 'žltý', 'synthetic-account-019'] as $term) {
            $this->listing(['search' => $term])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->id);
        }
        $this->listing(['search' => "' OR 1=1 --"])->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function linked_document_search_is_company_scoped_even_for_a_corrupted_cross_company_link(): void
    {
        $foreign = Company::create(['user_id' => $this->user->id, 'legal_name' => 'Another synthetic company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        foreach ([[$this->company, 'OWN-NEEDLE', 'Own document title'], [$foreign, 'FOREIGN-NEEDLE', 'Foreign document title']] as [$company, $number, $title]) {
            $document = BusinessDocument::create(['company_id' => $company->id, 'status' => BusinessDocumentStatus::Issued,
                'number' => $number, 'title' => $title, 'currency' => 'EUR', 'total' => '19.05', 'issue_date' => '2026-11-16']);
            $transaction = $this->movement(['reference' => null, 'match_status' => BankTransactionMatchStatus::Matched]);
            BankTransactionMatch::create(['bank_transaction_id' => $transaction->id, 'business_document_id' => $document->id,
                'matched_amount' => '19.05', 'match_type' => BankMatchType::Manual, 'matched_by_user_id' => $this->user->id, 'matched_at' => now()]);
        }
        $this->listing(['search' => 'own-needle'])->assertOk()->assertJsonCount(1, 'data');
        $this->listing(['search' => 'own document title'])->assertOk()->assertJsonCount(1, 'data');
        $this->listing(['search' => 'foreign-needle'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->listing(['search' => 'foreign document title'])->assertOk()->assertJsonPath('meta.total', 0);
        $otherUser = User::factory()->create();
        $foreign->update(['user_id' => $otherUser->id]);
        $this->actingAs($this->user)->getJson('/api/invoicing/companies/'.$foreign->id.'/bank-transactions?search=needle')->assertForbidden();
    }

    #[Test]
    public function invalid_ranges_and_nonfinite_or_unsafe_amounts_fail_validation_before_listing(): void
    {
        foreach ([['from' => '0000-01-01'], ['to' => '0000-01-01'], ['from' => '2026-02-30'],
            ['from' => '2026-11-17', 'to' => '2026-11-16'], ['amount_min' => '-1'], ['amount_min' => 'NaN'],
            ['amount_max' => '1e309'], ['amount_min' => '1e2'], ['amount_min' => '1.001'], ['amount_max' => '90071992547409.92'],
            ['amount_min' => '19.06', 'amount_max' => '19.05'], ['direction' => 'sideways'], ['search' => "bad\0query"], ['search' => "bad\xFFquery"],
            ['variable_symbol' => "bad\0symbol"], ['variable_symbol' => "bad\xFFsymbol"]] as $filters) {
            $this->listing($filters)->assertUnprocessable();
        }
        $this->listing(['amount_max' => '90071992547409.91'])->assertOk();
    }

    #[Test]
    public function stored_amounts_are_not_dropped_by_the_safe_filter_input_ceiling(): void
    {
        // SQLite legacy fixtures can retain values beyond the normal bank column precision.
        $transaction = $this->movement(['amount' => '90071992547410.00']);
        $this->listing()->assertOk()->assertJsonPath('data.0.id', $transaction->id)->assertJsonPath('meta.summary.debit_count', 1);
        $this->listing(['amount_min' => '1.00'])->assertOk()->assertJsonPath('meta.total', 1);
        $this->listing(['amount_max' => '90071992547409.91'])->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function email_sync_dispatch_does_not_use_ordinary_list_filter_validation(): void
    {
        $sync = ['sync' => '1', 'source' => 'email', 'sync_context' => str_repeat('a', 64), 'per_page' => 5];
        $this->listing($sync)->assertOk()->assertJsonPath('meta.sync.protocol', 2);
        $this->listing($sync + ['direction' => 'invalid', 'from' => '0000-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('sync_cursor')
            ->assertJsonMissingValidationErrors(['direction', 'from']);
    }
}
