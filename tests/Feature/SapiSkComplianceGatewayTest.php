<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Services\Invoicing\Efaktura\SapiSkComplianceGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SapiSkComplianceGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function skCompanyWithEfaktura(): array
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
            'vat_number' => 'SK2023980035',
            'iban' => 'SK3112000000198742637541',
            'street' => 'Bohunice 47',
            'city' => 'Bohunice',
            'postal_code' => '93505',
            'country' => 'SK',
            'vat_payer' => true,
            'vat_rate_default' => 23,
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_auto_send' => true,
                'efaktura_peppol_participant_id' => '0245:2023980035',
                'efaktura_sapi_client_id' => 'client-test',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('secret-test'),
            ],
        ]);

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Odberateľ s.r.o.',
            'registration_number' => '12345678',
            'tax_id' => '2123456789',
            'country' => 'SK',
        ]);

        return [$company, $contact];
    }

    #[Test]
    public function gateway_supports_configured_sk_b2b_invoice(): void
    {
        config([
            'efaktura.enabled' => true,
            'efaktura.providers.sapi_sk.base_url' => 'https://sapi.test',
        ]);

        [$company, $contact] = $this->skCompanyWithEfaktura();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261201',
            'subtotal' => 100,
            'tax_total' => 23,
            'total' => 123,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks.',
            'unit_price' => 100,
            'tax_rate' => 23,
            'line_total' => 123,
        ]);

        $this->assertTrue(app(SapiSkComplianceGateway::class)->supports($doc->fresh(['company', 'contact', 'lines'])));
    }

    #[Test]
    public function submit_persists_compliance_row_with_http_fake(): void
    {
        config([
            'efaktura.enabled' => true,
            'efaktura.providers.sapi_sk.base_url' => 'https://sapi.test',
        ]);

        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ]),
            'https://sapi.test/sapi/v1/document/send' => Http::response([
                'id' => 'doc-remote-99',
                'status' => 'submitted',
            ]),
        ]);

        [$company, $contact] = $this->skCompanyWithEfaktura();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261202',
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

        $result = app(ComplianceSubmissionService::class)->submitNow($doc->fresh(['company', 'contact', 'lines']));

        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
        $this->assertSame('doc-remote-99', $result->externalId);

        $this->assertDatabaseHas('business_document_compliance', [
            'business_document_id' => $doc->id,
            'provider' => 'peppol',
            'status' => 'submitted',
            'external_id' => 'doc-remote-99',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business_document.efaktura_submitted',
            'target_type' => 'business_document',
            'target_id' => $doc->id,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sapi.test/sapi/v1/document/send'
                && $request->hasHeader('X-Peppol-Participant-Id', '0245:2023980035');
        });
    }
}
