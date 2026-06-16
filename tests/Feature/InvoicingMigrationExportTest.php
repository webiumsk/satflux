<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyDocumentSequence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicingMigrationExportTest extends TestCase
{
    use RefreshDatabase;

    private User $proUser;

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
    }

    #[Test]
    public function migration_status_reports_server_data(): void
    {
        $company = $this->createCompany();
        CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client s.r.o.',
        ]);

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/status');

        $response->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.companies_count', 1)
            ->assertJsonPath('data.contacts_count', 1);
    }

    #[Test]
    public function migration_export_returns_evolu_snapshot_shape(): void
    {
        $company = $this->createCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client s.r.o.',
            'is_active' => true,
        ]);

        CompanyDocumentSequence::create([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'name' => 'Default',
            'format' => '{YYYY}{NNNN}',
            'reset_period' => 'yearly',
            'is_default' => true,
            'period_key' => '2026',
            'last_number' => 3,
        ]);

        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'title' => 'Invoice',
            'total' => 121,
            'subtotal' => 100,
            'tax_total' => 21,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $document->id,
            'sort_order' => 1,
            'name' => 'Service',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'tax_rate' => 21,
            'line_total' => 121,
        ]);

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export');

        $response->assertOk()
            ->assertJsonPath('data.company.0.id', $company->id)
            ->assertJsonPath('data.company.0.legalName', 'Acme s.r.o.')
            ->assertJsonPath('data.contact.0.companyId', $company->id)
            ->assertJsonPath('data.document.0.documentType', 'invoice')
            ->assertJsonPath('data.documentLine.0.documentId', $document->id)
            ->assertJsonCount(1, 'data.numberSeries')
            ->assertJsonPath('meta.counts.company', 1)
            ->assertJsonPath('meta.counts.document', 1);
    }

    #[Test]
    public function migration_export_is_empty_for_user_without_companies(): void
    {
        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export');

        $response->assertNotFound();
    }

    #[Test]
    public function migration_endpoints_require_pro_plan(): void
    {
        $freeUser = User::factory()->create();

        $this->actingAs($freeUser)
            ->getJson('/api/invoicing/migration/status')
            ->assertForbidden();
    }

    private function createCompany(): Company
    {
        return Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
            'vat_status' => 'none',
        ]);
    }
}
