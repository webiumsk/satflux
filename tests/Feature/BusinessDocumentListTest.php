<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentListTest extends TestCase
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
            'max_ln_addresses' => null,
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
            'legal_name' => 'List Co',
            'trade_name' => 'List Trade',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);
    }

    #[Test]
    public function company_summary_returns_lightweight_payload(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$this->company->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.id', $this->company->id)
            ->assertJsonPath('data.legal_name', 'List Co')
            ->assertJsonPath('data.trade_name', 'List Trade')
            ->assertJsonPath('data.has_bank_account', false)
            ->assertJsonMissingPath('data.app_settings')
            ->assertJsonMissingPath('data.contacts');
    }

    #[Test]
    public function document_list_returns_bulk_capabilities_and_omits_heavy_fields(): void
    {
        $older = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 10,
            'currency' => 'EUR',
            'issue_date' => now()->subDays(2),
            'buyer_snapshot' => ['name' => 'Should not leak'],
            'internal_note' => 'secret',
            'created_at' => now()->subDays(2),
        ]);

        $latest = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Paid,
            'number' => '20260002',
            'total' => 20,
            'currency' => 'EUR',
            'issue_date' => now()->subDay(),
            'paid_at' => now(),
            'amount_paid' => 20,
            'created_at' => now()->subDay(),
        ]);

        $bankTransaction = BankTransaction::create([
            'company_id' => $this->company->id,
            'booked_at' => now(),
            'amount' => 10,
            'currency' => 'EUR',
            'direction' => 'credit',
            'dedupe_hash' => 'list-test-hash',
        ]);

        BankTransactionMatch::create([
            'bank_transaction_id' => $bankTransaction->id,
            'business_document_id' => $older->id,
            'matched_amount' => 10,
            'match_type' => 'manual',
            'matched_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents?type=invoice&per_page=25");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.can_delete', true)
            ->assertJsonPath('data.0.can_unmark_paid', true)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.can_delete', false)
            ->assertJsonMissingPath('data.0.buyer_snapshot')
            ->assertJsonMissingPath('data.0.internal_note')
            ->assertJsonMissingPath('data.0.note_footer');
    }

    #[Test]
    public function document_list_uses_bounded_query_count(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            BusinessDocument::create([
                'company_id' => $this->company->id,
                'type' => 'invoice',
                'status' => BusinessDocumentStatus::Issued,
                'number' => sprintf('2026%04d', $i),
                'total' => $i,
                'currency' => 'EUR',
                'issue_date' => now()->subDays(12 - $i),
                'created_at' => now()->subDays(12 - $i),
            ]);
        }

        $this->actingAs($this->user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson("/api/invoicing/companies/{$this->company->id}/documents?type=invoice&per_page=25")
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(
            15,
            $queryCount,
            "Expected bounded queries for document list, got {$queryCount}",
        );
    }
}
