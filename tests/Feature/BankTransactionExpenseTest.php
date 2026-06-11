<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankTransactionExpenseTest extends TestCase
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
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'iban' => 'SK3112000000198747547509',
        ]);

        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);
    }

    #[Test]
    public function company_summary_includes_has_bank_account_flag(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$this->company->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.has_bank_account', true);
    }

    #[Test]
    public function debit_transaction_can_create_linked_expense(): void
    {
        $transaction = BankTransaction::create([
            'company_id' => $this->company->id,
            'booked_at' => now(),
            'amount' => 866.05,
            'currency' => 'EUR',
            'direction' => BankTransactionDirection::Debit,
            'match_status' => BankTransactionMatchStatus::Unmatched,
            'variable_symbol' => '4234',
            'counterparty_name' => 'HOSTEL LETOV',
            'reference' => 'Platba kartou 4234**3873, HOSTEL LETOV.',
            'dedupe_hash' => 'debit-expense-test',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/{$transaction->id}/create-expense",
            [
                'title' => 'HOSTEL LETOV',
                'supplier' => 'HOSTEL LETOV',
                'total' => 866.05,
                'variable_symbol' => '4234',
                'mark_paid' => true,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.expense.status', BusinessExpenseStatus::Paid->value);
        $response->assertJsonPath('data.transaction.match_status', BankTransactionMatchStatus::Matched->value);
        $response->assertJsonPath('data.transaction.expense.internal_number', fn ($v) => is_string($v) && $v !== '');

        $transaction->refresh();
        $this->assertNotNull($transaction->business_expense_id);
    }

    #[Test]
    public function credit_transaction_cannot_create_expense(): void
    {
        $transaction = BankTransaction::create([
            'company_id' => $this->company->id,
            'booked_at' => now(),
            'amount' => 100,
            'currency' => 'EUR',
            'direction' => BankTransactionDirection::Credit,
            'match_status' => BankTransactionMatchStatus::Unmatched,
            'dedupe_hash' => 'credit-no-expense',
        ]);

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/{$transaction->id}/create-expense",
            ['title' => 'Test'],
        )->assertStatus(422);
    }
}
