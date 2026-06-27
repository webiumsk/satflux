<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentProformaTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

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
    }

    #[Test]
    public function user_can_create_and_list_proforma(): void
    {
        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'proforma',
                'currency' => 'EUR',
                'lines' => [
                    ['name' => 'Záloha', 'quantity' => 1, 'unit_price' => 500],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.type', 'proforma');

        $list = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/documents?type=proforma");

        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    #[Test]
    public function paid_proforma_can_create_final_invoice_once(): void
    {
        $proforma = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Proforma,
            'status' => BusinessDocumentStatus::Draft,
            'total' => 500,
            'currency' => 'EUR',
            'lines' => [],
        ]);
        $proforma->setRelation('company', $this->company);
        app(BusinessDocumentIssueService::class)->issue($proforma);
        $proforma->update([
            'status' => BusinessDocumentStatus::Paid,
            'paid_at' => now(),
            'amount_paid' => 500,
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/create-final-invoice");

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'invoice');
        $response->assertJsonPath('data.source_document_id', $proforma->id);
        $response->assertJsonPath('data.status', 'paid');
        $invoiceNumber = $response->json('data.number');
        $this->assertNotNull($invoiceNumber);
        $this->assertStringStartsWith('INV', $invoiceNumber);
        $response->assertJsonPath('data.title', "Faktúra {$invoiceNumber}");
        $this->assertStringNotContainsString('Zálohová', $response->json('data.title'));
        $this->assertStringContainsString($proforma->number, $response->json('data.note_above_lines'));
        $this->assertSame('500.00', $response->json('data.amount_paid'));

        $again = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/create-final-invoice");

        $again->assertStatus(422);
    }

    #[Test]
    public function two_issued_proformas_get_sequential_numbers(): void
    {
        $year = now()->format('Y');

        foreach (['First', 'Second'] as $label) {
            $create = $this->actingAs($this->proUser)
                ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                    'type' => 'proforma',
                    'title' => $label,
                    'currency' => 'EUR',
                    'lines' => [
                        ['name' => $label, 'quantity' => 1, 'unit_price' => 10],
                    ],
                ]);
            $create->assertCreated();
            $create->assertJsonPath('data.type', 'proforma');

            $id = $create->json('data.id');
            $issue = $this->actingAs($this->proUser)
                ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$id}/issue");
            $issue->assertOk();
        }

        $numbers = BusinessDocument::query()
            ->where('company_id', $this->company->id)
            ->where('type', BusinessDocumentType::Proforma)
            ->whereNotNull('number')
            ->orderBy('number')
            ->pluck('number')
            ->all();

        $this->assertSame(["PF{$year}0001", "PF{$year}0002"], $numbers);
    }

    #[Test]
    public function mark_paid_on_draft_proforma_issues_and_pays(): void
    {
        $proforma = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Proforma,
            'status' => BusinessDocumentStatus::Draft,
            'total' => 120,
            'currency' => 'EUR',
            'lines' => [],
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/mark-paid");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'paid');
        $this->assertNotNull($response->json('data.number'));
        $this->assertStringStartsWith('PF', $response->json('data.number'));
    }

    #[Test]
    public function unpaid_proforma_cannot_create_final_invoice(): void
    {
        $proforma = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Proforma,
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'PF20260001',
            'total' => 100,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/create-final-invoice");

        $response->assertStatus(422);
    }
}
