<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
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

class BusinessDocumentCreditNoteTest extends TestCase
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
            'name' => 'Client s.r.o.',
        ]);
    }

    #[Test]
    public function user_can_create_blank_credit_note(): void
    {
        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'credit_note',
                'company_contact_id' => $this->contact->id,
                'issue_date' => '2026-06-04',
                'due_date' => '2026-06-04',
                'lines' => [
                    ['name' => 'Storno', 'quantity' => 1, 'unit_price' => 50],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.type', 'credit_note');
        $create->assertJsonPath('data.source_document_id', null);
    }

    #[Test]
    public function credit_note_from_paid_invoice_copies_lines_and_link(): void
    {
        $invoice = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'currency' => 'EUR',
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $invoice->id,
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'line_discount_percent' => 0,
            'tax_rate' => 0,
            'line_total' => 100,
        ]);

        app(BusinessDocumentIssueService::class)->issue($invoice);
        $invoice->update(['status' => BusinessDocumentStatus::Paid, 'paid_at' => now()]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/credit-note-from-invoice", [
                'invoice_id' => $invoice->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'credit_note');
        $response->assertJsonPath('data.source_document_id', $invoice->id);
        $response->assertJsonPath('data.status', 'draft');
        $this->assertStringContainsString($invoice->number, $response->json('data.note_above_lines'));
        $this->assertCount(1, $response->json('data.lines') ?? []);
    }

    #[Test]
    public function credit_note_from_invoice_requires_issued_or_paid_invoice(): void
    {
        $invoice = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => '2026-06-04',
            'due_date' => '2026-06-18',
            'currency' => 'EUR',
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
        ]);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/credit-note-from-invoice", [
                'invoice_id' => $invoice->id,
            ])
            ->assertStatus(422);
    }
}
