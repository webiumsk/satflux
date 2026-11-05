<?php

namespace Tests\Feature;

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
use App\Services\Invoicing\BusinessExpenseBulkService;
use App\Services\Invoicing\BusinessExpenseReconciliationService;
use App\Services\Invoicing\BusinessExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class BusinessExpenseBulkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    /** Prepare a synthetic legacy company with permission to exercise bulk expense actions. */
    protected function setUp(): void
    {
        parent::setUp();
        config(['invoicing.local_first' => false]);

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

    /**
     * Create both selection orders with one independent expense and one confirmed bank-linked payment.
     *
     * @return array{BusinessExpense, BusinessExpense, BankTransaction}
     */
    private function mixedSelection(bool $linkedFirst): array
    {
        $expenses = [];
        for ($index = 1; $index <= 2; $index++) {
            $expenses[] = BusinessExpense::create([
                'id' => '00000000-0000-4000-8000-'.str_pad((string) $index, 12, '0', STR_PAD_LEFT),
                'company_id' => $this->company->id,
                'status' => BusinessExpenseStatus::Recorded,
                'internal_number' => 'BULK-'.$index,
                'variable_symbol' => '001234',
                'issue_date' => '2026-11-01',
                'total' => '10.00',
                'currency' => 'EUR',
            ]);
        }
        $linked = $expenses[$linkedFirst ? 0 : 1];
        $independent = $expenses[$linkedFirst ? 1 : 0];
        $transaction = BankTransaction::create([
            'company_id' => $this->company->id,
            'booked_at' => '2026-11-03 14:35:00',
            'amount' => '10.00',
            'currency' => 'EUR',
            'direction' => BankTransactionDirection::Debit,
            'match_status' => BankTransactionMatchStatus::Unmatched,
            'variable_symbol' => '001234',
            'source' => 'csv',
            'dedupe_hash' => (string) Str::uuid(),
        ]);
        app(BusinessExpenseReconciliationService::class)->match($this->company, $transaction, $linked->id, $this->user->id);

        return [$linked, $independent, $transaction];
    }

    /** Report mixed-selection outcomes truthfully while preserving linked payment evidence in either order. */
    #[Test]
    #[TestWith(['http', false])]
    #[TestWith(['http', true])]
    #[TestWith(['service', false])]
    #[TestWith(['service', true])]
    public function bulk_cancellation_skips_linked_expenses_and_processes_independent_rows(string $entrypoint, bool $linkedFirst): void
    {
        [$linked, $independent, $transaction] = $this->mixedSelection($linkedFirst);
        $selection = $linkedFirst ? [$linked, $independent] : [$independent, $linked];
        $ids = array_map(fn (BusinessExpense $expense) => $expense->id, $selection);
        $beforeExpense = $linked->fresh()->getAttributes();
        $beforeTransaction = $transaction->fresh()->getAttributes();
        $beforeAudit = AuditLog::whereIn('target_id', [$linked->id, $transaction->id])->orderBy('id')->get()->toArray();
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/bulk";
        $this->actingAs($this->user);

        $result = $entrypoint === 'http'
            ? $this->postJson($url, ['action' => 'cancel', 'expense_ids' => $ids])->assertOk()->json('data')
            : app(BusinessExpenseBulkService::class)->cancel(collect($selection));

        $this->assertSame(['processed' => 1, 'skipped' => 1], $result);
        $this->assertSame(BusinessExpenseStatus::Cancelled, $independent->fresh()->status);
        $this->assertNotNull($independent->fresh()->getRawOriginal('cancelled_at'));
        $this->assertSame(['processed' => 0, 'skipped' => 2], app(BusinessExpenseBulkService::class)->cancel(
            collect(array_map(fn (BusinessExpense $expense) => $expense->fresh(), $selection)),
        ));
        // HTTP selection preserves the existing exclusion of already-cancelled expenses.
        $this->postJson($url, ['action' => 'cancel', 'expense_ids' => $ids])->assertOk()
            ->assertJsonPath('data.processed', 0)->assertJsonPath('data.skipped', 1);
        $this->assertSame($beforeExpense, $linked->fresh()->getAttributes());
        $this->assertSame($beforeTransaction, $transaction->fresh()->getAttributes());
        $this->assertSame($beforeAudit, AuditLog::whereIn('target_id', [$linked->id, $transaction->id])->orderBy('id')->get()->toArray());

        $this->postJson("/api/invoicing/companies/{$this->company->id}/bank-transactions/{$transaction->id}/unmatch")->assertOk();
        $unlinkedTransaction = $transaction->fresh()->getAttributes();
        $this->postJson($url, ['action' => 'cancel', 'expense_ids' => $ids])->assertOk()
            ->assertJsonPath('data.processed', 1)->assertJsonPath('data.skipped', 0);
        $this->assertSame(BusinessExpenseStatus::Cancelled, $linked->fresh()->status);
        $this->assertSame($unlinkedTransaction, $transaction->fresh()->getAttributes());
    }

    /** Propagate unrelated or mixed validation errors instead of counting them as known linked-payment skips. */
    #[Test]
    #[TestWith([['other' => ['unrelated_validation']]])]
    #[TestWith([['status' => ['expense_linked_payment', 'unrelated_validation']]])]
    #[TestWith([['status' => ['expense_linked_payment'], 'other' => ['unrelated_validation']]])]
    #[TestWith([['expense_linked_payment' => ['unrelated_validation']]])]
    public function bulk_cancellation_rethrows_validation_outside_the_exact_linked_payment_shape(array $errors): void
    {
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'BULK-VALIDATION',
            'issue_date' => '2026-11-01',
            'total' => '10.00',
            'currency' => 'EUR',
        ]);
        $this->mock(BusinessExpenseService::class, function (MockInterface $mock) use ($errors): void {
            $mock->shouldReceive('cancel')->once()->andThrow(ValidationException::withMessages($errors));
        });

        try {
            app(BusinessExpenseBulkService::class)->cancel(collect([$expense]));
            $this->fail('Unexpected validation errors must propagate to the caller.');
        } catch (ValidationException $error) {
            $this->assertSame($errors, $error->errors());
        }
        $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
    }

    /** Preserve unexpected failure propagation instead of returning a successful skipped counter. */
    #[Test]
    public function bulk_cancellation_rethrows_non_validation_failures(): void
    {
        $expense = new BusinessExpense(['status' => BusinessExpenseStatus::Recorded]);
        $this->mock(BusinessExpenseService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancel')->once()->andThrow(new RuntimeException('Synthetic cancellation failure'));
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synthetic cancellation failure');

        app(BusinessExpenseBulkService::class)->cancel(collect([$expense]));
    }
}
