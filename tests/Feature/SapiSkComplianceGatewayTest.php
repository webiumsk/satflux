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
use App\Services\Invoicing\Efaktura\SapiSkClient;
use App\Services\Invoicing\Efaktura\SapiSkComplianceGateway;
use App\Support\Invoicing\BuyerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'vat_status' => 'payer',
            'vat_rate_default' => 23,
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_auto_send' => true,
                'efaktura_sapi_base_url' => 'https://sapi.test',
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
            'efaktura.allowed_sapi_hosts' => ['sapi.test'],
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
        Cache::flush();

        config([
            'efaktura.enabled' => true,
            'efaktura.allowed_sapi_hosts' => ['sapi.test'],
        ]);

        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ]),
            'https://sapi.test/sapi/v1/document/send' => Http::response([
                'providerDocumentId' => 'doc-remote-99',
                'status' => 'ACCEPTED',
            ], 202),
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

        $this->assertSame(ComplianceSubmissionStatus::Approved, $result->status);
        $this->assertSame('doc-remote-99', $result->externalId);

        $this->assertDatabaseHas('business_document_compliance', [
            'business_document_id' => $doc->id,
            'provider' => 'peppol',
            'status' => 'approved',
            'external_id' => 'doc-remote-99',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business_document.efaktura_submitted',
            'target_type' => 'business_document',
            'target_id' => $doc->id,
        ]);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sapi.test/sapi/v1/document/send') {
                return false;
            }

            $body = $request->data();

            return $request->hasHeader('X-Peppol-Participant-Id', '0245:2023980035')
                && ($body['payloadFormat'] ?? null) === 'XML'
                && ($body['metadata']['receiverParticipantId'] ?? null) === '0245:2123456789'
                && str_contains((string) ($body['payload'] ?? ''), 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        });
    }

    #[Test]
    public function access_token_cache_is_scoped_by_client_secret(): void
    {
        Cache::flush();

        config(['efaktura.allowed_sapi_hosts' => ['sapi.test']]);

        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::sequence()
                ->push(['access_token' => 'token-one', 'expires_in' => 3600])
                ->push(['access_token' => 'token-two', 'expires_in' => 3600]),
        ]);

        $client = app(SapiSkClient::class);

        $this->assertSame('token-one', $client->accessToken('shared-client', 'secret-one', 'https://sapi.test'));
        $this->assertSame('token-two', $client->accessToken('shared-client', 'secret-two', 'https://sapi.test'));

        Http::assertSentCount(2);
    }

    #[Test]
    public function submit_uses_frozen_buyer_snapshot_for_receiver_metadata_and_ubl_endpoint(): void
    {
        Cache::flush();

        config([
            'efaktura.enabled' => true,
            'efaktura.allowed_sapi_hosts' => ['sapi.test'],
        ]);

        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ]),
            'https://sapi.test/sapi/v1/document/send' => Http::response([
                'providerDocumentId' => 'doc-remote-99',
                'status' => 'ACCEPTED',
            ], 202),
        ]);

        [$company, $contact] = $this->skCompanyWithEfaktura();
        $contact->update([
            'tax_id' => '1111111111',
            'peppol_participant_id' => '0245:1111111111',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'buyer_snapshot' => BuyerSnapshot::fromContact($contact->fresh()),
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261206',
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

        $contact->update([
            'tax_id' => '2222222222',
            'peppol_participant_id' => '0245:2222222222',
        ]);

        $result = app(ComplianceSubmissionService::class)->submitNow($doc->fresh(['company', 'contact', 'lines']));

        $this->assertSame(ComplianceSubmissionStatus::Approved, $result->status);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sapi.test/sapi/v1/document/send') {
                return false;
            }

            $body = $request->data();
            $payload = (string) ($body['payload'] ?? '');

            return ($body['metadata']['receiverParticipantId'] ?? null) === '0245:1111111111'
                && str_contains($payload, 'schemeID="0245">1111111111</cbc:EndpointID>')
                && ! str_contains($payload, '2222222222');
        });
    }

    #[Test]
    public function gateway_does_not_support_non_vat_payer_company(): void
    {
        config(['efaktura.enabled' => true]);

        [$company, $contact] = $this->skCompanyWithEfaktura();
        $company->update(['vat_status' => 'none', 'vat_payer' => false]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261204',
            'subtotal' => 50,
            'tax_total' => 0,
            'total' => 50,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $this->assertFalse(app(SapiSkComplianceGateway::class)->supports($doc->fresh(['company', 'contact'])));
    }

    #[Test]
    public function gateway_does_not_support_partial_vat_payer_company(): void
    {
        config(['efaktura.enabled' => true]);

        [$company, $contact] = $this->skCompanyWithEfaktura();
        $company->update(['vat_status' => 'partial', 'vat_payer' => true]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261205',
            'subtotal' => 50,
            'tax_total' => 0,
            'total' => 50,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $this->assertFalse(app(SapiSkComplianceGateway::class)->supports($doc->fresh(['company', 'contact'])));
    }

    #[Test]
    public function submit_fails_when_recipient_peppol_id_missing(): void
    {
        Http::fake();

        config([
            'efaktura.enabled' => true,
            'efaktura.allowed_sapi_hosts' => ['sapi.test'],
        ]);

        [$company] = $this->skCompanyWithEfaktura();

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Bez ID',
            'country' => 'SK',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20261203',
            'total' => 10,
            'subtotal' => 10,
            'tax_total' => 0,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $result = app(ComplianceSubmissionService::class)->submitNow($doc->fresh(['company', 'contact', 'lines']));

        $this->assertSame(ComplianceSubmissionStatus::Failed, $result->status);
        $this->assertStringContainsString('Recipient Peppol', (string) $result->message);
        Http::assertNothingSent();
    }
}
