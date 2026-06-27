<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

    protected Company $company;

    protected CompanyContact $contact;

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

        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->company = Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
        ]);

        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);

        $this->contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'SITMAR, s.r.o.',
        ]);
    }

    #[Test]
    public function user_can_create_and_list_quote(): void
    {
        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'quote',
                'company_contact_id' => $this->contact->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'lines' => [
                    ['name' => 'Služba', 'quantity' => 1, 'unit_price' => 87],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.type', 'quote');

        $list = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents?type=quote");

        $list->assertOk();
        $list->assertJsonCount(1, 'data');
    }

    #[Test]
    public function approved_quote_can_create_invoice_once(): void
    {
        $quote = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Quote,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 87,
            'tax_total' => 0,
            'total' => 87,
            'payment_btc_enabled' => false,
            'payment_bank_enabled' => true,
        ]);

        app(BusinessDocumentIssueService::class)->issue($quote);
        $quote->refresh();
        $this->assertSame(BusinessDocumentQuoteStatus::Pending, $quote->quote_status);
        $this->assertSame(BusinessDocumentQuoteStatus::Pending, $quote->resolvedQuoteStatus());

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$quote->id}/approve-quote")
            ->assertOk()
            ->assertJsonPath('data.quote_status', 'approved');

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$quote->id}/create-invoice-from-quote");

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'invoice');
        $response->assertJsonPath('data.source_document_id', $quote->id);
        $this->assertNotNull($response->json('data.number'));

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$quote->id}/create-invoice-from-quote")
            ->assertStatus(422);
    }

    #[Test]
    public function cannot_create_invoice_from_pending_quote(): void
    {
        $quote = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Quote,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 50,
            'tax_total' => 0,
            'total' => 50,
        ]);

        app(BusinessDocumentIssueService::class)->issue($quote);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$quote->id}/create-invoice-from-quote")
            ->assertStatus(422);
    }

    #[Test]
    public function quote_filter_pending_excludes_expired_by_date(): void
    {
        $pending = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Quote,
            'status' => BusinessDocumentStatus::Issued,
            'quote_status' => BusinessDocumentQuoteStatus::Pending,
            'number' => 'PON2026001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
        ]);

        BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Quote,
            'status' => BusinessDocumentStatus::Issued,
            'quote_status' => BusinessDocumentQuoteStatus::Pending,
            'number' => 'PON2026002',
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 20,
            'tax_total' => 0,
            'total' => 20,
        ]);

        $list = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents?type=quote&filter=pending");

        $list->assertOk();
        $list->assertJsonCount(1, 'data');
        $list->assertJsonPath('data.0.id', $pending->id);
    }
}
