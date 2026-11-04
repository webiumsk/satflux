<?php

namespace Tests\Feature;

use App\Enums\BankImportSource;
use App\Enums\BankMatchType;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use App\Services\Invoicing\BankStatementImportService;
use App\Services\Invoicing\BusinessDocumentPaymentMatcher;
use App\Services\Invoicing\BusinessExpensePaymentMatcher;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankExpensePaymentMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->company();
    }

    private function company(): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    private function expense(array $overrides = []): BusinessExpense
    {
        return BusinessExpense::create(array_merge([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => (string) Str::uuid(),
            'external_number' => 'INV-001234',
            'variable_symbol' => '001234',
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
            'dedupe_hash' => (string) Str::uuid(),
            'source' => BankImportSource::Email->value,
        ], $overrides));
    }

    private function match(BankTransaction $transaction): bool
    {
        return app(BusinessDocumentPaymentMatcher::class)->tryAutoMatch($transaction)['matched'];
    }

    #[Test]
    public function email_debit_links_existing_expense_and_uses_booking_date_with_bounded_audit(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();

        $result = app(BusinessDocumentPaymentMatcher::class)->tryAutoMatch($transaction, $this->company->user_id);

        $this->assertTrue($result['matched']);
        $this->assertNull($result['match']);
        $this->assertSame($expense->id, $transaction->fresh()->business_expense_id);
        $this->assertSame(BankTransactionMatchStatus::Matched, $transaction->fresh()->match_status);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertSame('2026-11-03', $expense->fresh()->paid_at->toDateString());
        $audit = AuditLog::where('action', 'bank_transaction.expense_matched')->sole();
        $this->assertSame($this->company->user_id, $audit->user_id);
        $this->assertSame([
            'company_id' => $this->company->id,
            'business_expense_id' => $expense->id,
            'match_type' => 'auto',
        ], $audit->metadata);
        $this->assertDatabaseCount('bank_transaction_matches', 0);
        $this->assertFalse($this->match($transaction));
        $this->assertSame(1, AuditLog::where('action', 'bank_transaction.expense_matched')->count());
    }

    /** Verify VS precedence and full-reference fallback without accepting partial document-number matches. */
    #[Test]
    #[TestWith(['001234', 'INV-001234', '001234', null, true])]
    #[TestWith([null, 'INV-001234', '001234', null, true])]
    #[TestWith(['', 'INV-001234', '001234', null, true])]
    #[TestWith(['001234', 'INV-001234', '1234', 'INV-001234', false])]
    #[TestWith(['7777', 'INV-001234', '001234', 'INV-001234', false])]
    #[TestWith([null, ' INV-001234 ', null, ' INV-001234 ', true])]
    #[TestWith([null, 'INV-001234', null, 'payment INV-001234', false])]
    #[TestWith(['invalid', 'INV-001234', null, 'INV-001234', false])]
    #[TestWith(['001234', 'INV-001234', 'invalid', 'INV-001234', false])]
    #[TestWith([null, 'INV-001234', null, null, false])]
    #[TestWith(['001234', 'INV-001234', null, 'INV-001234', true])]
    public function matching_hints_have_exact_vs_precedence(
        ?string $expenseVs,
        string $externalNumber,
        ?string $bankVs,
        ?string $reference,
        bool $expected,
    ): void {
        $expense = $this->expense(['variable_symbol' => $expenseVs, 'external_number' => $externalNumber]);
        $transaction = $this->movement(['variable_symbol' => $bankVs, 'reference' => $reference]);

        $this->assertSame($expected, $this->match($transaction));
        $this->assertSame($expected ? BusinessExpenseStatus::Paid : BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    #[TestWith(['amount', '123.44'])]
    #[TestWith(['amount', '123.46'])]
    #[TestWith(['amount', '0'])]
    #[TestWith(['amount', '-123.45'])]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['currency', ''])]
    #[TestWith(['currency', 'EU'])]
    #[TestWith(['direction', 'credit'])]
    #[TestWith(['source', 'csv'])]
    #[TestWith(['source', 'wise'])]
    #[TestWith(['source', 'manual'])]
    #[TestWith(['source', 'camt053'])]
    #[TestWith(['match_status', 'ignored'])]
    #[TestWith(['match_status', 'matched'])]
    #[TestWith(['counterparty_name', 'Stav na účte'])]
    #[TestWith(['reference', 'Stav na ucte'])]
    public function ineligible_email_movements_do_not_pay_expenses(string $field, string $value): void
    {
        $expense = $this->expense();
        $transaction = $this->movement([$field => $value]);

        $this->assertFalse($this->match($transaction));
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertNull($expense->fresh()->paid_at);
    }

    #[Test]
    public function ambiguity_and_another_company_never_choose_the_first_expense(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        $transaction = $this->movement();
        $this->assertFalse($this->match($transaction));
        $second->delete();
        $first->update(['company_id' => $this->company()->id]);
        $this->assertFalse($this->match($transaction));
        $this->assertSame(BusinessExpenseStatus::Recorded, $first->fresh()->status);
    }

    #[Test]
    #[TestWith(['status', 'paid'])]
    #[TestWith(['status', 'cancelled'])]
    #[TestWith(['paid_at', '2026-10-31 00:00:00'])]
    #[TestWith(['cancelled_at', '2026-10-31 00:00:00'])]
    public function inconsistent_or_closed_expenses_are_never_matched(string $field, string $value): void
    {
        $expense = $this->expense();
        $expense->forceFill([$field => $value])->save();

        $this->assertFalse($this->match($this->movement()));
        $this->assertDatabaseMissing('bank_transactions', ['business_expense_id' => $expense->id]);
    }

    #[Test]
    public function any_existing_expense_or_invoice_link_excludes_the_movement(): void
    {
        $expense = $this->expense();
        $linked = $this->movement(['business_expense_id' => $expense->id, 'match_status' => 'unmatched']);
        $this->assertFalse($this->match($linked));
        $this->assertFalse($this->match($this->movement()));

        $linked->delete();
        $transaction = $this->movement();
        $document = BusinessDocument::create([
            'company_id' => $this->company->id, 'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued, 'number' => 'SYNTHETIC-1',
            'issue_date' => '2026-11-01', 'currency' => 'EUR', 'total' => '123.45',
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $transaction->id, 'business_document_id' => $document->id,
            'matched_amount' => '123.45', 'match_type' => BankMatchType::Auto, 'matched_at' => now(),
        ]);
        $this->assertFalse($this->match($transaction));
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    #[Test]
    public function two_stale_movements_cannot_pay_one_expense_twice(): void
    {
        $expense = $this->expense();
        $first = $this->movement();
        $second = $this->movement();
        $this->assertTrue($this->match($first));
        $this->assertFalse($this->match($second));
        $this->assertSame(1, BankTransaction::where('business_expense_id', $expense->id)->count());
    }

    #[Test]
    public function large_decimal_amounts_preserve_exact_cents(): void
    {
        $expense = $this->expense(['total' => '999999999999.98']);
        $this->assertFalse($this->match($this->movement(['amount' => '999999999999.99'])));
        $this->assertTrue($this->match($this->movement(['amount' => '999999999999.98'])));
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
    }

    #[Test]
    public function candidate_queries_exclude_unrelated_amounts_and_currencies_before_locking(): void
    {
        $exact = $this->expense(['currency' => 'eur']);
        $this->expense(['total' => '123.46']);
        $this->expense(['currency' => 'USD']);
        $this->expense(['company_id' => $this->company()->id]);
        $transaction = $this->movement();
        $retrieved = [];
        $event = 'eloquent.retrieved: '.BusinessExpense::class;
        app('events')->listen($event, function (BusinessExpense $expense) use (&$retrieved) {
            $retrieved[] = $expense->id;
        });
        try {
            $this->assertTrue($this->match($transaction));
        } finally {
            app('events')->forget($event);
        }

        $this->assertSame([$exact->id], array_values(array_unique($retrieved)));
        $this->assertSame($exact->id, $transaction->fresh()->business_expense_id);
    }

    #[Test]
    public function api_preserves_recorded_credit_when_text_suggests_a_debit(): void
    {
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year',
            'max_stores' => 3, 'max_api_keys' => 3,
            'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        Subscription::create([
            'user_id' => $this->company->user_id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $transaction = $this->movement(['direction' => 'credit', 'reference' => 'Platba kartou']);

        $this->actingAs($this->company->user)->getJson('/api/invoicing/companies/'.$this->company->id.'/bank-transactions')
            ->assertOk()->assertJsonPath('data.0.id', $transaction->id)
            ->assertJsonPath('data.0.direction', 'debit')
            ->assertJsonPath('data.0.recorded_direction', 'credit');

        $this->actingAs(User::factory()->create())->getJson('/api/invoicing/companies/'.$this->company->id.'/bank-transactions')
            ->assertForbidden();
    }

    #[Test]
    public function fresh_database_state_overrides_a_stale_unmatched_instance(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        BankTransaction::whereKey($transaction->id)->update(['match_status' => 'ignored']);
        $this->assertFalse($this->match($transaction));
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    /** A failed audit insert must roll back both financial links and retain the unpaid expense. */
    #[Test]
    public function audit_failure_rolls_back_both_bookkeeping_updates(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $event = 'eloquent.creating: '.AuditLog::class;
        app('events')->listen($event, function (AuditLog $audit) {
            if ($audit->action === 'bank_transaction.expense_matched') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            $this->match($transaction);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        } finally {
            app('events')->forget($event);
        }
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertNull($expense->fresh()->paid_at);
        $this->assertSame(BankTransactionMatchStatus::Unmatched, $transaction->fresh()->match_status);
        $this->assertNull($transaction->fresh()->business_expense_id);
        $this->assertSame(0, AuditLog::where('action', 'bank_transaction.expense_matched')->count());
    }

    #[Test]
    public function only_new_email_rows_or_explicit_batch_matching_reconcile_existing_expenses(): void
    {
        $rows = [new ParsedBankTransaction(
            bookedAt: Carbon::parse('2026-11-03 14:35:00'), amount: 123.45, currency: 'EUR',
            direction: BankTransactionDirection::Debit, variableSymbol: '001234',
        )];
        $service = app(BankStatementImportService::class);
        $initial = $service->persistRows($this->company, null, $rows, BankImportSource::Email);
        $this->assertSame(0, $initial['auto_matched']);
        $expense = $this->expense();
        $duplicate = $service->persistRows($this->company, null, $rows, BankImportSource::Email);
        $this->assertSame(0, $duplicate['imported']);
        $this->assertSame(0, $duplicate['auto_matched']);
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $result = app(BusinessDocumentPaymentMatcher::class)->autoMatchBatch($initial['batch']->transactions);
        $this->assertSame(1, $result['auto_matched']);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
    }

    #[Test]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234'])]
    #[TestWith(['Debet. Suma: 123,45. VS: 001234. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,450 EUR. VS: 001234. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. 31.02.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: invalid. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 12.34. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 123 456. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. Informacia pre prijemcu: 03.11.2026'])]
    #[TestWith(["31.02.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nSuma: 123,45 EUR. 03.11.2026. VS: 001234"])]
    public function parser_rejects_unqualified_debits_before_persistence_and_payment(string $body): void
    {
        $expense = $this->expense();
        $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body);
        $result = app(BankStatementImportService::class)->persistRows($this->company, null, $rows, BankImportSource::Email);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['auto_matched']);
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertDatabaseCount('bank_transactions', 0);
    }

    #[Test]
    public function a_qualified_parsed_email_debit_pays_only_the_existing_exact_expense(): void
    {
        $expense = $this->expense();
        $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', 'Debet. Suma: 123,45 EUR. VS: 001234. 03.11.2026');
        $result = app(BankStatementImportService::class)->persistRows($this->company, null, $rows, BankImportSource::Email);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['auto_matched']);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
    }

    /** Reject malformed raw storage before ORM casts can normalize it; PostgreSQL rejects these fixtures itself. */
    #[Test]
    #[TestWith(['booked_at', '2026-02-30 12:00:00'])]
    #[TestWith(['booked_at', '2026-11-03 24:01:00'])]
    #[TestWith(['booked_at', 'not-a-date'])]
    #[TestWith(['amount', '123.4501'])]
    #[TestWith(['amount', 'not-an-amount'])]
    public function invalid_stored_values_are_checked_before_cast_normalization(string $field, string $value): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only malformed-storage fixture; PostgreSQL rejects or normalizes these values at storage.');
        }
        $expense = $this->expense();
        $transaction = $this->movement();
        DB::table('bank_transactions')->where('id', $transaction->id)->update([$field => $value]);

        $this->assertFalse(app(BusinessExpensePaymentMatcher::class)->tryAutoMatch($transaction));
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }
}
