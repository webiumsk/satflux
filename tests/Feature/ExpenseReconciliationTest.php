<?php

namespace Tests\Feature;

use App\Enums\BankImportSource;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessExpenseExcelImportService;
use App\Services\Invoicing\BusinessExpenseReconciliationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ExpenseReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        config(['invoicing.local_first' => false]);
        $this->user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year',
            'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Synthetic Reconciliation Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $this->actingAs($this->user);
    }

    private function expense(array $overrides = []): BusinessExpense
    {
        return BusinessExpense::create(array_merge([
            'company_id' => $this->company->id,
            'internal_number' => (string) Str::uuid(),
            'external_number' => 'INV-001234',
            'title' => 'Synthetic Hosting',
            'variable_symbol' => '001234',
            'status' => BusinessExpenseStatus::Recorded,
            'issue_date' => '2026-10-30',
            'total' => '123.45',
            'currency' => 'EUR',
        ], $overrides));
    }

    private function movement(array $overrides = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'company_id' => $this->company->id,
            'booked_at' => '2026-11-03 14:35:00',
            'amount' => '123.45',
            'currency' => 'EUR',
            'direction' => BankTransactionDirection::Debit,
            'match_status' => BankTransactionMatchStatus::Unmatched,
            'variable_symbol' => '001234',
            'source' => BankImportSource::Csv->value,
            'dedupe_hash' => (string) Str::uuid(),
        ], $overrides));
    }

    private function bankUrl(BankTransaction $transaction, string $action): string
    {
        return "/api/invoicing/companies/{$this->company->id}/bank-transactions/{$transaction->id}/{$action}";
    }

    private function duplicateUrl(): string
    {
        return "/api/invoicing/companies/{$this->company->id}/expenses/duplicate-suggestions";
    }

    #[Test]
    public function suggestions_are_read_only_and_ambiguous_candidates_require_explicit_confirmation(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        $transaction = $this->movement();

        $response = $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column(array_column($response->json('data'), 'expense'), 'id'));
        $this->assertNull($transaction->fresh()->business_expense_id);
        $this->assertSame(BusinessExpenseStatus::Recorded, $first->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'bank_transaction.expense_matched')->count());

        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $second->id])
            ->assertOk()->assertJsonPath('data.transaction.expense.id', $second->id);
        $this->assertSame(BusinessExpenseStatus::Recorded, $first->fresh()->status);
        $this->assertSame(BusinessExpenseStatus::Paid, $second->fresh()->status);
        $this->assertSame('2026-11-03', $second->fresh()->paid_at->toDateString());
    }

    #[Test]
    #[TestWith(['csv'])]
    #[TestWith(['camt053'])]
    #[TestWith(['wise'])]
    #[TestWith(['manual'])]
    #[TestWith(['email'])]
    public function full_exact_recorded_debits_from_supported_sources_can_be_confirmed(string $source): void
    {
        $expense = $this->expense();
        $transaction = $this->movement(['source' => $source]);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()
            ->assertJsonPath('data.0.reason', 'variable_symbol');
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])
            ->assertOk()->assertJsonPath('data.expense.status', 'paid');
        $this->assertSame($expense->id, $transaction->fresh()->business_expense_id);
    }

    #[Test]
    public function same_pair_retries_preserve_paid_date_and_one_audit_entry(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $url = $this->bankUrl($transaction, 'match-expense');

        $this->postJson($url, ['expense_id' => $expense->id])->assertOk();
        $originalDate = $expense->fresh()->getRawOriginal('paid_at');
        $this->travel(5)->days();
        $this->postJson($url, ['expense_id' => $expense->id])->assertOk();

        $this->assertSame($originalDate, $expense->fresh()->getRawOriginal('paid_at'));
        $this->assertSame(1, AuditLog::where('action', 'bank_transaction.expense_matched')->count());
    }

    #[Test]
    public function retry_cannot_silently_reverse_a_deliberate_unpaid_edit(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $url = $this->bankUrl($transaction, 'match-expense');
        $this->postJson($url, ['expense_id' => $expense->id])->assertOk();
        $expense->refresh()->update(['status' => 'recorded', 'paid_at' => null]);

        $this->postJson($url, ['expense_id' => $expense->id])->assertUnprocessable();
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertNull($expense->fresh()->paid_at);
    }

    #[Test]
    #[TestWith(['amount', '123.44'])]
    #[TestWith(['amount', '123.46'])]
    #[TestWith(['amount', '123.451'])]
    #[TestWith(['amount', '0'])]
    #[TestWith(['amount', '-123.45'])]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['currency', ''])]
    #[TestWith(['currency', 'EU'])]
    #[TestWith(['direction', 'credit'])]
    #[TestWith(['match_status', 'ignored'])]
    #[TestWith(['reference', 'Stav na ucte'])]
    #[TestWith(['counterparty_name', 'Stav na účte'])]
    #[TestWith(['variable_symbol', 'invalid'])]
    #[TestWith(['variable_symbol', '9999'])]
    public function ineligible_movements_cannot_pay_an_expense(string $field, string $value): void
    {
        $expense = $this->expense();
        $transaction = $this->movement([$field => $value]);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertNull($transaction->fresh()->business_expense_id);
    }

    #[Test]
    public function free_text_debit_inference_cannot_override_recorded_credit_evidence(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement(['direction' => 'credit', 'reference' => 'Platba kartou, SYNTHETIC SHOP']);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
    }

    #[Test]
    public function full_reference_fallback_does_not_accept_partial_reference_or_override_a_different_bank_symbol(): void
    {
        $expense = $this->expense(['variable_symbol' => null]);
        $transaction = $this->movement(['variable_symbol' => null, 'reference' => 'INV-001234']);
        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()
            ->assertJsonPath('data.0.reason', 'payment_reference');
        $transaction->update(['reference' => 'Payment INV-001234']);
        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        $transaction->update(['reference' => 'INV-001234', 'variable_symbol' => '9999']);
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
    }

    #[Test]
    public function confirmation_rechecks_changes_since_the_suggestion_was_loaded(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(1, 'data');
        $expense->update(['total' => '123.46']);

        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    public function another_transaction_cannot_claim_the_same_expense_even_if_its_status_is_manually_reset(): void
    {
        $expense = $this->expense();
        $first = $this->movement();
        $second = $this->movement();
        $this->postJson($this->bankUrl($first, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $expense->refresh()->update(['status' => 'recorded', 'paid_at' => null]);

        $this->getJson($this->bankUrl($second, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->bankUrl($second, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
        $this->assertNull($second->fresh()->business_expense_id);
    }

    #[Test]
    public function an_already_matched_movement_cannot_be_reassigned_or_ignored(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $first->id])->assertOk();

        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $second->id])->assertUnprocessable();
        $this->postJson($this->bankUrl($transaction, 'ignore'))->assertUnprocessable();
        $this->assertSame($first->id, $transaction->fresh()->business_expense_id);
    }

    #[Test]
    public function removing_a_link_preserves_paid_evidence_and_records_the_detach(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $paidAt = $expense->fresh()->getRawOriginal('paid_at');

        $this->postJson($this->bankUrl($transaction, 'unmatch'))->assertOk()->assertJsonPath('data.business_expense_id', null);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertSame($paidAt, $expense->fresh()->getRawOriginal('paid_at'));
        $this->assertSame(1, AuditLog::where('action', 'bank_transaction.expense_unmatched')->count());
    }

    #[Test]
    public function paid_cancelled_and_foreign_expenses_are_not_match_candidates(): void
    {
        $transaction = $this->movement();
        $paid = $this->expense(['status' => 'paid', 'paid_at' => now()]);
        $cancelled = $this->expense(['status' => 'cancelled']);
        $foreignCompany = Company::create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Other Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $foreign = $this->expense(['company_id' => $foreignCompany->id]);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        foreach ([$paid, $cancelled] as $expense) {
            $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
        }
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $foreign->id])->assertNotFound();
        $foreignTransaction = $this->movement(['company_id' => $foreignCompany->id]);
        $this->getJson($this->bankUrl($foreignTransaction, 'expense-suggestions'))->assertNotFound();
    }

    #[Test]
    public function forbidden_company_and_guest_requests_cannot_read_or_attach_candidates(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertForbidden();
        $this->actingAs($otherUser)->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertForbidden();
        $this->actingAs($otherUser)->postJson($this->duplicateUrl(), ['total' => '123.45', 'currency' => 'EUR'])->assertForbidden();
    }

    #[Test]
    public function local_first_companies_never_send_server_expense_reconciliation(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->company->update(['app_settings' => ['local_first' => true]]);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertUnprocessable();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
        $this->postJson($this->duplicateUrl(), ['total' => '123.45', 'currency' => 'EUR'])->assertUnprocessable();
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    public function audit_failure_rolls_back_both_bookkeeping_updates(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"')) {
                throw new RuntimeException('Synthetic audit failure');
            }
        });
        try {
            app(BusinessExpenseReconciliationService::class)->match($this->company, $transaction, $expense->id, $this->user->id);
            $this->fail('Expected synthetic failure');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic audit failure', $error->getMessage());
        }
        $this->assertNull($transaction->fresh()->business_expense_id);
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    public function duplicate_suggestions_use_available_fields_with_stable_reason_priority_and_are_advisory(): void
    {
        $expense = $this->expense(['external_number' => ' inv-001234 ', 'title' => '  ŽLTÝ   Hosting  ']);
        $draft = ['external_number' => 'INV-001234', 'title' => 'žltý hosting', 'issue_date' => '2026-10-30', 'total' => '123.45', 'currency' => 'EUR'];

        $this->postJson($this->duplicateUrl(), $draft)->assertOk()
            ->assertJsonPath('data.0.expense.id', $expense->id)->assertJsonPath('data.0.reason', 'external_number');
        $this->postJson($this->duplicateUrl(), array_merge($draft, ['external_number' => null]))->assertOk()
            ->assertJsonPath('data.0.reason', 'title_date_amount');
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertDatabaseCount('business_expenses', 1);
    }

    #[Test]
    public function duplicate_search_excludes_the_editing_record_and_other_companies_and_cancelled_rows(): void
    {
        $expense = $this->expense();
        $cancelled = $this->expense(['status' => 'cancelled']);
        $otherCompany = Company::create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Other Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $foreign = $this->expense(['company_id' => $otherCompany->id]);
        $draft = ['external_number' => 'INV-001234', 'total' => '123.45', 'currency' => 'EUR', 'exclude_id' => $expense->id];

        $this->postJson($this->duplicateUrl(), $draft)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->duplicateUrl(), array_merge($draft, ['exclude_id' => $foreign->id]))->assertNotFound();
        $this->assertSame(BusinessExpenseStatus::Cancelled, $cancelled->fresh()->status);
    }

    #[Test]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['total', '123.44'])]
    #[TestWith(['total', '123.451'])]
    #[TestWith(['total', '0'])]
    public function weak_amount_or_currency_similarity_is_not_a_duplicate_warning(string $field, string $value): void
    {
        $this->expense();
        $draft = ['external_number' => 'INV-001234', 'title' => 'Synthetic Hosting', 'issue_date' => '2026-10-30', 'total' => '123.45', 'currency' => 'EUR'];

        $this->postJson($this->duplicateUrl(), array_merge($draft, [$field => $value]))->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function leading_zero_document_numbers_remain_distinct_and_title_requires_the_same_date(): void
    {
        $this->expense(['external_number' => '001234']);
        $this->postJson($this->duplicateUrl(), [
            'external_number' => '1234', 'title' => 'Synthetic Hosting',
            'issue_date' => '2026-10-31', 'total' => '123.45', 'currency' => 'EUR',
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function duplicate_results_are_deterministic_and_bounded_to_twenty(): void
    {
        $ids = [];
        for ($index = 0; $index < 25; $index++) {
            $ids[] = $this->expense()->id;
        }
        sort($ids, SORT_STRING);
        $response = $this->postJson($this->duplicateUrl(), [
            'external_number' => 'INV-001234', 'total' => '123.45', 'currency' => 'EUR',
        ])->assertOk()->assertJsonCount(20, 'data');

        $this->assertSame(array_slice($ids, 0, 20), array_column(array_column($response->json('data'), 'expense'), 'id'));
    }

    /** @param list<list<string>> $rows */
    private function importFile(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Title', 'Document', 'Date', 'Amount', 'Currency']);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('synthetic-expenses.csv', $content);
    }

    /** @return array<string, int> */
    private function importMapping(): array
    {
        return ['title' => 0, 'external_number' => 1, 'issue_date' => 2, 'total' => 3, 'currency' => 4];
    }

    #[Test]
    public function mapped_import_preview_detects_existing_and_in_file_duplicates_without_mutations_or_skips(): void
    {
        $expense = $this->expense();
        $rows = [
            ['Different title', ' inv-001234 ', '2026-11-01', '123.45', 'EUR'],
            ['New hosting', 'NEW-7', '2026-11-01', '40.00', 'EUR'],
            ['New hosting', 'new-7', '2026-11-01', '40.00', 'EUR'],
            ['Unique service', 'UNIQUE-1', '2026-11-01', '20.00', 'EUR'],
        ];
        $service = app(BusinessExpenseExcelImportService::class);
        $preview = $service->preview($this->company, $this->importFile($rows), $this->importMapping());

        $this->assertCount(2, $preview['duplicate_warnings']);
        $this->assertSame(2, $preview['duplicate_warnings'][0]['row']);
        $this->assertSame($expense->id, $preview['duplicate_warnings'][0]['expense']['id']);
        $this->assertFalse($preview['duplicate_warnings'][0]['in_file']);
        $this->assertSame(4, $preview['duplicate_warnings'][1]['row']);
        $this->assertSame('preview-row-3', $preview['duplicate_warnings'][1]['expense']['id']);
        $this->assertTrue($preview['duplicate_warnings'][1]['in_file']);
        $this->assertDatabaseCount('business_expenses', 1);

        $result = $service->import($this->company, $this->importFile($rows), $this->importMapping());
        $this->assertSame(4, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('business_expenses', 5);
    }

    #[Test]
    public function legacy_preview_recalculates_warning_from_the_submitted_mapping(): void
    {
        $expense = $this->expense();
        $rows = [['Different title', 'INV-001234', '2026-11-01', '123.45', 'EUR']];
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel/preview";
        $wrongMapping = array_merge($this->importMapping(), ['external_number' => 0]);

        $this->postJson($url, ['file' => $this->importFile($rows), 'mapping' => json_encode($wrongMapping)])
            ->assertOk()->assertJsonCount(0, 'data.duplicate_warnings');
        $this->postJson($url, ['file' => $this->importFile($rows), 'mapping' => json_encode($this->importMapping())])
            ->assertOk()->assertJsonPath('data.duplicate_warnings.0.expense.id', $expense->id);
    }

    #[Test]
    public function import_warning_lookup_is_one_company_query_and_twenty_results_even_for_many_file_rows(): void
    {
        $this->expense();
        $reads = 0;
        DB::listen(function (QueryExecuted $query) use (&$reads): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "business_expenses"')) {
                $reads++;
            }
        });
        $preview = app(BusinessExpenseExcelImportService::class)->preview(
            $this->company,
            $this->importFile(array_fill(0, 25, ['Synthetic Hosting', 'INV-001234', '2026-10-30', '123.45', 'EUR'])),
            $this->importMapping(),
        );

        $this->assertCount(20, $preview['duplicate_warnings']);
        $this->assertSame(1, $reads);
    }

    #[Test]
    public function import_preflight_excludes_cancelled_and_foreign_expenses(): void
    {
        $this->expense(['status' => 'cancelled']);
        $otherCompany = Company::create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Other Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $this->expense(['company_id' => $otherCompany->id]);
        $preview = app(BusinessExpenseExcelImportService::class)->preview(
            $this->company,
            $this->importFile([['Synthetic Hosting', 'INV-001234', '2026-10-30', '123.45', 'EUR']]),
            $this->importMapping(),
        );

        $this->assertSame([], $preview['duplicate_warnings']);
    }
}
