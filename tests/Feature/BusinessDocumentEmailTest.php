<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Mail\BusinessDocumentEmail;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

    protected Company $company;

    protected BusinessDocument $document;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

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

        $contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Client Ltd',
            'email' => 'client@example.com',
            'default_payment_terms_days' => 14,
        ]);

        $this->document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 121,
            'currency' => 'EUR',
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'variable_symbol' => '20260001',
            'lines' => [],
        ]);
    }

    #[Test]
    public function email_preview_requires_issued_document(): void
    {
        $draft = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'total' => 50,
            'currency' => 'EUR',
            'lines' => [],
        ]);

        $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents/{$draft->id}/email-preview")
            ->assertStatus(422);
    }

    #[Test]
    public function email_preview_returns_rendered_template(): void
    {
        $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents/{$this->document->id}/email-preview")
            ->assertOk()
            ->assertJsonPath('data.to', 'client@example.com')
            ->assertJsonPath('data.template_key', 'invoice')
            ->assertJsonPath('data.subject', 'Acme s.r.o. - Faktúra 20260001');
    }

    #[Test]
    public function pro_user_can_send_document_email_with_pdf(): void
    {
        $pdf = \Mockery::mock(BusinessDocumentPdfService::class);
        $pdf->shouldReceive('renderBinary')->once()->andReturn('%PDF-1.4 test');
        $this->instance(BusinessDocumentPdfService::class, $pdf);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$this->document->id}/send-email", [
                'to' => ['billing@client.com'],
                'subject' => 'Faktúra 20260001',
                'body' => "Dobrý deň,\n\nv prílohe faktúra.",
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.sent_to.0', 'billing@client.com');
        $response->assertJsonPath('data.email_sent_at', fn ($v) => $v !== null);

        $this->document->refresh();
        $this->assertNotNull($this->document->email_sent_at);

        Mail::assertSent(BusinessDocumentEmail::class, function (BusinessDocumentEmail $mail) {
            return $mail->subjectLine === 'Faktúra 20260001'
                && str_starts_with($mail->pdfBinary, '%PDF')
                && in_array('billing@client.com', $mail->toAddresses, true);
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business_document.email_sent',
            'target_id' => $this->document->id,
        ]);
    }
}
