<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\BusinessDocumentLine;
use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\Efaktura\ComplianceStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceStatusSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refresh_row_updates_status_from_send_detail_endpoint(): void
    {
        config([
            'efaktura.enabled' => true,
            'efaktura.allowed_sapi_hosts' => ['sapi.test'],
            'efaktura.providers.sapi_sk.send_detail_path' => '/sapi/v1/document/send/{id}',
        ]);

        [$company, $contact] = $this->skCompanyWithEfaktura();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261203',
            'subtotal' => 50,
            'tax_total' => 0,
            'total' => 50,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Položka',
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $row = BusinessDocumentCompliance::query()->create([
            'business_document_id' => $doc->id,
            'provider' => ComplianceProvider::Peppol,
            'status' => ComplianceSubmissionStatus::Submitted,
            'external_id' => 'doc-remote-99',
            'response_payload' => ['status' => 'PROCESSING'],
            'submitted_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ]),
            'https://sapi.test/sapi/v1/document/send/doc-remote-99' => Http::response([
                'providerDocumentId' => 'doc-remote-99',
                'status' => 'DELIVERED',
            ]),
        ]);

        app(ComplianceStatusSyncService::class)->refreshRow($row);

        $row->refresh();
        $this->assertSame(ComplianceSubmissionStatus::Approved, $row->status);
        $this->assertNotNull($row->resolved_at);
    }

    /**
     * @return array{0: Company, 1: CompanyContact}
     */
    protected function skCompanyWithEfaktura(): array
    {
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
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'registration_number' => '47615681',
            'tax_id' => '2023980035',
            'country' => 'SK',
            'vat_payer' => true,
            'vat_status' => 'payer',
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_sapi_base_url' => 'https://sapi.test',
                'efaktura_peppol_participant_id' => '0245:2023980035',
                'efaktura_sapi_client_id' => 'client',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('secret'),
            ],
        ]);

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Odberateľ s.r.o.',
            'country' => 'SK',
            'tax_id' => '2123456789',
        ]);

        return [$company, $contact];
    }
}
