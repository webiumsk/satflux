<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyDocumentSequence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyDataResetTest extends TestCase
{
    use RefreshDatabase;

    private User $proUser;

    private Company $company;

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
    }

    #[Test]
    public function user_can_reset_company_operational_data(): void
    {
        $contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Client s.r.o.',
        ]);

        BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'lines' => [],
        ]);

        $sequence = CompanyDocumentSequence::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'name' => 'Faktúra',
            'format' => 'RRRRCCCC',
            'reset_period' => 'yearly',
            'is_default' => true,
            'period_key' => '2026',
            'last_number' => 12,
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/reset-data", [
                'confirm_name' => 'Acme s.r.o.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.documents', 1);
        $response->assertJsonPath('data.contacts', 1);

        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
        $this->assertDatabaseMissing('company_contacts', ['company_id' => $this->company->id]);
        $this->assertDatabaseMissing('business_documents', ['company_id' => $this->company->id]);
        $this->assertSame(0, $sequence->fresh()->last_number);
    }

    #[Test]
    public function reset_requires_matching_company_name(): void
    {
        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/reset-data", [
                'confirm_name' => 'Wrong name',
            ]);

        $response->assertUnprocessable();
    }
}
