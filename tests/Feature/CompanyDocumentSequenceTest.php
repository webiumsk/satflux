<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyDocumentSequence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyDocumentSequenceTest extends TestCase
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
    public function user_can_list_and_create_number_series(): void
    {
        $list = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/number-series");

        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json('data')));

        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/number-series", [
                'name' => 'Faktúra export',
                'document_type' => 'invoice',
                'format' => 'FAKRRRRCCCC',
                'reset_period' => 'yearly',
                'is_default' => false,
                'last_number' => 10,
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.format', 'FAKRRRRCCCC');
        $create->assertJsonPath('data.next_number_preview', 'FAK20260011');
    }

    #[Test]
    public function user_can_preview_next_document_number(): void
    {
        $response = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/number-series/preview?type=invoice");

        $response->assertOk();
        $response->assertJsonPath('data.document_type', 'invoice');
        $year = now()->format('Y');
        $response->assertJsonPath('data.next_number', "{$year}0001");

        $proforma = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/number-series/preview?type=proforma");

        $proforma->assertOk();
        $proforma->assertJsonPath('data.next_number', "ZAL{$year}0001");
    }

    #[Test]
    public function preview_advances_after_existing_proforma_numbers(): void
    {
        $year = now()->format('Y');
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => \App\Enums\BusinessDocumentType::Proforma,
            'status' => BusinessDocumentStatus::Issued,
            'number' => "ZAL{$year}0001",
            'total' => 10,
            'currency' => 'EUR',
        ]);

        CompanyDocumentSequence::query()
            ->where('company_id', $this->company->id)
            ->where('document_type', 'proforma')
            ->update(['last_number' => 0]);

        $preview = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/number-series/preview?type=proforma");

        $preview->assertOk();
        $preview->assertJsonPath('data.next_number', "ZAL{$year}0002");
    }

    #[Test]
    public function issue_uses_default_series_format(): void
    {
        CompanyDocumentSequence::query()
            ->where('company_id', $this->company->id)
            ->where('document_type', 'invoice')
            ->update(['is_default' => false]);

        CompanyDocumentSequence::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'name' => 'Custom',
            'format' => 'XRRRRCC',
            'reset_period' => 'yearly',
            'is_default' => true,
            'period_key' => now()->format('Y'),
            'last_number' => 5,
        ]);

        $document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'total' => 100,
            'currency' => 'EUR',
            'lines' => [],
        ]);
        $document->setRelation('company', $this->company);

        app(BusinessDocumentIssueService::class)->issue($document);

        $this->assertSame('X202606', $document->fresh()->number);
    }
}
