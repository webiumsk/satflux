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
use App\Services\Invoicing\BusinessExpenseService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ExpenseReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    /** Prepare an authenticated synthetic company with isolated legacy invoicing. */
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

    /** Create a synthetic expense with configurable financial and identity evidence. */
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

    /** Create a distinct recorded bank debit for reconciliation scenarios. */
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

    /** Build the company-scoped endpoint for a synthetic bank movement. */
    private function bankUrl(BankTransaction $transaction, string $action): string
    {
        return "/api/invoicing/companies/{$this->company->id}/bank-transactions/{$transaction->id}/{$action}";
    }

    /** Build the current company's advisory duplicate-preview endpoint. */
    private function duplicateUrl(): string
    {
        return "/api/invoicing/companies/{$this->company->id}/expenses/duplicate-suggestions";
    }

    /** Keep ambiguous suggestions read-only until the user selects one expense. */
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

    /** Allow explicit full-amount confirmation across supported persisted bank sources. */
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

    /** Keep successful retries idempotent for payment dates and both audit histories. */
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
        $this->assertSame(1, AuditLog::where('action', 'business_expense.marked_paid')->where('target_id', $expense->id)->count());
    }

    /** Reject retries that would overwrite a deliberate change to independent payment state. */
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

    /** Reject weak or incompatible movement evidence without marking an expense paid. */
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

    /** Preserve recorded credit evidence when display heuristics infer an outgoing movement. */
    #[Test]
    public function free_text_debit_inference_cannot_override_recorded_credit_evidence(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement(['direction' => 'credit', 'reference' => 'Platba kartou, SYNTHETIC SHOP']);

        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnprocessable();
    }

    /** Require complete fallback references and preserve the precedence of a supplied bank symbol. */
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

    /** Revalidate financial evidence changed after a suggestion was displayed. */
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

    /** Prevent a second movement from claiming an expense with an existing bank link. */
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

    /** Preserve an established expense link until the user explicitly removes it. */
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

    /** Detach bookkeeping relationships without rewriting independent payment evidence. */
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

    /** Exclude paid, cancelled and foreign-company expenses from eligible confirmations. */
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

    /** Deny another authenticated user access to company-scoped reconciliation endpoints. */
    #[Test]
    public function foreign_user_requests_cannot_read_or_attach_candidates(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertForbidden();
        $this->actingAs($otherUser)->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertForbidden();
        $this->actingAs($otherUser)->postJson($this->duplicateUrl(), ['total' => '123.45', 'currency' => 'EUR'])->assertForbidden();
    }

    /** Require authentication independently on each new reconciliation endpoint. */
    #[Test]
    public function unauthenticated_requests_cannot_access_the_three_reconciliation_endpoints(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        auth()->forgetGuards();
        $this->getJson($this->bankUrl($transaction, 'expense-suggestions'))->assertUnauthorized();
        auth()->forgetGuards();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertUnauthorized();
        auth()->forgetGuards();
        $this->postJson($this->duplicateUrl(), ['total' => '123.45', 'currency' => 'EUR'])->assertUnauthorized();
    }

    /** Expose the confirmed payment in expense history exactly once across retries. */
    #[Test]
    public function confirming_a_match_adds_one_expense_history_event_with_payment_evidence(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $url = $this->bankUrl($transaction, 'match-expense');
        $this->postJson($url, ['expense_id' => $expense->id])->assertOk();
        $this->postJson($url, ['expense_id' => $expense->id])->assertOk();

        $this->getJson("/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/history")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'business_expense.marked_paid')
            ->assertJsonPath('data.0.metadata', [
                'company_id' => $this->company->id,
                'bank_transaction_id' => $transaction->id,
                'match_type' => 'manual',
            ]);
    }

    /** Roll back both bookkeeping records and bank history if expense-history persistence fails. */
    #[Test]
    public function expense_history_audit_failure_rolls_back_the_bank_audit_and_both_records(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"')
                && in_array('business_expense.marked_paid', $query->bindings, true)) {
                throw new RuntimeException('Synthetic expense history failure');
            }
        });
        try {
            app(BusinessExpenseReconciliationService::class)->match($this->company, $transaction, $expense->id, $this->user->id);
            $this->fail('Expected the synthetic expense history failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic expense history failure', $error->getMessage());
        }
        $this->assertNull($transaction->fresh()->business_expense_id);
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
        $this->assertSame(0, AuditLog::whereIn('action', ['bank_transaction.expense_matched', 'business_expense.marked_paid'])->count());
    }

    /** Reject changed linked financial values while preserving payment status and history. */
    #[Test]
    #[TestWith(['total', '123.46'])]
    #[TestWith(['total', '0'])]
    #[TestWith(['total', '123.451'])]
    #[TestWith(['currency', 'USD'])]
    public function linked_expense_financial_edits_require_unlinking_first(string $field, string $value): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $paidAt = $expense->fresh()->getRawOriginal('paid_at');

        $this->patchJson("/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}", [$field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame('123.45', $expense->fresh()->total);
        $this->assertSame('EUR', $expense->fresh()->currency);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertSame($paidAt, $expense->fresh()->getRawOriginal('paid_at'));
        $this->assertSame($expense->id, $transaction->fresh()->business_expense_id);
        $this->assertSame(BankTransactionMatchStatus::Matched, $transaction->fresh()->match_status);
        $this->assertSame(0, AuditLog::where('action', 'business_expense.updated')->count());
    }

    /** Allow canonical equivalents and descriptive edits without altering the established match. */
    #[Test]
    #[TestWith(['123.4500', 'eur'])]
    #[TestWith(['000123.45', 'EUR'])]
    #[TestWith(['1.2345e2', 'eUr'])]
    public function equivalent_financial_values_and_nonfinancial_edits_preserve_the_match(string $total, string $currency): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $paidAt = $expense->fresh()->getRawOriginal('paid_at');

        $this->patchJson("/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}", [
            'total' => $total, 'currency' => $currency, 'title' => 'Corrected nonfinancial description',
        ])->assertOk();
        $this->patchJson("/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}", [
            'internal_note' => 'Synthetic nonfinancial note',
        ])->assertOk();

        $this->assertSame('123.45', $expense->fresh()->total);
        $this->assertSame('Corrected nonfinancial description', $expense->fresh()->title);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertSame($paidAt, $expense->fresh()->getRawOriginal('paid_at'));
        $this->assertSame($expense->id, $transaction->fresh()->business_expense_id);
        $this->assertSame(BankTransactionMatchStatus::Matched, $transaction->fresh()->match_status);
        $this->assertSame(1, AuditLog::where('action', 'business_expense.marked_paid')->count());
    }

    /** Allow financial correction after unlinking while preserving independent paid history. */
    #[Test]
    public function an_unlinked_paid_expense_can_be_edited_without_rewriting_paid_history(): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $paidAt = $expense->fresh()->getRawOriginal('paid_at');
        $this->postJson($this->bankUrl($transaction, 'unmatch'))->assertOk();

        $this->patchJson("/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}", ['total' => '130.00', 'currency' => 'USD'])->assertOk();
        $this->assertSame('130.00', $expense->fresh()->total);
        $this->assertSame('USD', $expense->fresh()->currency);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertSame($paidAt, $expense->fresh()->getRawOriginal('paid_at'));
        $this->assertNull($transaction->fresh()->business_expense_id);
    }

    /** Reject linked status changes without modifying either record or its existing audit history. */
    #[Test]
    #[TestWith(['unmark'])]
    #[TestWith(['cancel'])]
    public function linked_expenses_cannot_be_unmarked_or_cancelled(string $action): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $beforeExpense = $expense->fresh()->getAttributes();
        $beforeTransaction = $transaction->fresh()->getAttributes();
        $beforeAudit = AuditLog::query()->orderBy('id')->get()->toArray();
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}";

        $response = $action === 'unmark' ? $this->postJson($url.'/unmark-paid') : $this->deleteJson($url);

        $response->assertUnprocessable()->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status', ['expense_linked_payment']);
        $this->assertSame($beforeExpense, $expense->fresh()->getAttributes());
        $this->assertSame($beforeTransaction, $transaction->fresh()->getAttributes());
        $this->assertSame($beforeAudit, AuditLog::query()->orderBy('id')->get()->toArray());
    }

    /** Allow repeated independent status actions after the user explicitly removes the bank relationship. */
    #[Test]
    #[TestWith(['unmark', 'recorded'])]
    #[TestWith(['cancel', 'cancelled'])]
    public function removing_the_bank_link_allows_independent_status_changes(string $action, string $status): void
    {
        $expense = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $expense->id])->assertOk();
        $paidAt = $expense->fresh()->getRawOriginal('paid_at');
        $this->postJson($this->bankUrl($transaction, 'unmatch'))->assertOk();
        $beforeTransaction = $transaction->fresh()->getAttributes();
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $action === 'unmark' ? $this->postJson($url.'/unmark-paid') : $this->deleteJson($url);
            $response->assertOk()->assertJsonPath('data.status', $status);
        }

        $this->assertSame($beforeTransaction, $transaction->fresh()->getAttributes());
        $this->assertSame($action === 'unmark' ? null : $paidAt, $expense->fresh()->getRawOriginal('paid_at'));
        if ($action === 'cancel') {
            $this->assertNotNull($expense->fresh()->getRawOriginal('cancelled_at'));
        }
        $auditAction = $action === 'unmark' ? 'business_expense.unmarked_paid' : 'business_expense.cancelled';
        $this->assertSame(2, AuditLog::where('action', $auditAction)->where('target_id', $expense->id)->count());
    }

    /** Reload current bank-link state before accepting status changes from a stale expense instance. */
    #[Test]
    #[TestWith(['unmarkPaid'])]
    #[TestWith(['cancel'])]
    public function stale_expense_objects_cannot_bypass_linked_status_guards(string $method): void
    {
        $stale = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $stale->id])->assertOk();
        $beforeExpense = $stale->fresh()->getAttributes();
        $beforeTransaction = $transaction->fresh()->getAttributes();
        $beforeAudit = AuditLog::query()->orderBy('id')->get()->toArray();

        try {
            app(BusinessExpenseService::class)->{$method}($stale);
            $this->fail('Stale expense objects must not bypass the current bank relationship.');
        } catch (ValidationException $error) {
            $this->assertSame(['status' => ['expense_linked_payment']], $error->errors());
        }

        $this->assertSame($beforeExpense, $stale->fresh()->getAttributes());
        $this->assertSame($beforeTransaction, $transaction->fresh()->getAttributes());
        $this->assertSame($beforeAudit, AuditLog::query()->orderBy('id')->get()->toArray());
    }

    /** Reload financial state before allowing edits from a stale expense instance. */
    #[Test]
    public function stale_expense_objects_cannot_bypass_the_current_bank_link(): void
    {
        $stale = $this->expense();
        $transaction = $this->movement();
        $this->postJson($this->bankUrl($transaction, 'match-expense'), ['expense_id' => $stale->id])->assertOk();
        try {
            app(BusinessExpenseService::class)->update($stale, ['total' => '200.00']);
            $this->fail('A stale expense object must not overwrite matched financial evidence.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('total', $error->errors());
        }
        $this->assertSame('123.45', $stale->fresh()->total);
        $this->assertSame(BusinessExpenseStatus::Paid, $stale->fresh()->status);
    }

    /** Keep local-first expense reconciliation outside the legacy server storage path. */
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

    /** Roll back both financial records when the bank audit cannot be persisted. */
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

    /** Report available duplicate evidence with stable reason priority and no expense mutations. */
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

    /** Isolate duplicate advice from the edited record, cancelled rows and other companies. */
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

    /** Avoid duplicate warnings when amount or currency evidence is incompatible. */
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

    /** Preserve significant document-number zeros and require dates for title-based advice. */
    #[Test]
    public function leading_zero_document_numbers_remain_distinct_and_title_requires_the_same_date(): void
    {
        $this->expense(['external_number' => '001234']);
        $this->postJson($this->duplicateUrl(), [
            'external_number' => '1234', 'title' => 'Synthetic Hosting',
            'issue_date' => '2026-10-31', 'total' => '123.45', 'currency' => 'EUR',
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    /** Bound advisory results while retaining deterministic candidate ordering. */
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

    /** Preview existing and in-file similarities without changing import outcomes. */
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

    /** Recompute duplicate advice from the column mapping explicitly submitted by the user. */
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

    /** Keep mapped import advice bounded without issuing one company query per file row. */
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

    /** Exclude cancelled and foreign-company rows from import duplicate advice. */
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

    /** Exclude crossed amount/currency pairs before hydrating import-preview candidates. */
    #[Test]
    public function import_preflight_loads_only_the_files_exact_amount_and_normalized_currency_pairs(): void
    {
        $euro = $this->expense(['external_number' => 'EU-1', 'total' => '100.00', 'currency' => 'eUr']);
        $dollar = $this->expense(['external_number' => 'US-1', 'total' => '200.00', 'currency' => 'USD']);
        $this->expense(['external_number' => 'EU-1', 'total' => '100.00', 'currency' => 'USD']);
        $this->expense(['external_number' => 'US-1', 'total' => '200.00', 'currency' => 'EUR']);
        $this->expense(['external_number' => 'EU-1', 'total' => '100.01', 'currency' => 'EUR']);
        $loaded = [];
        Event::listen('eloquent.retrieved: '.BusinessExpense::class, function (BusinessExpense $expense) use (&$loaded): void {
            $loaded[] = $expense->id;
        });
        $preview = app(BusinessExpenseExcelImportService::class)->preview($this->company, $this->importFile([
            ['Euro charge', 'EU-1', '2026-11-01', '100.00', 'EUR'],
            ['Dollar charge', 'US-1', '2026-11-01', '200.00', 'USD'],
        ]), $this->importMapping());

        $this->assertCount(2, $preview['duplicate_warnings']);
        $this->assertEqualsCanonicalizing([$euro->id, $dollar->id], $loaded);
    }
}
