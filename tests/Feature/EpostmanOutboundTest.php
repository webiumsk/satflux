<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\ComplianceSubmissionStatus;
use App\Jobs\SubmitBusinessDocumentCompliance;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\EfakturaOutboundAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Services\Invoicing\Efaktura\ComplianceStatusSyncService;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Services\Invoicing\Efaktura\EpostmanOutboundSubmissionService;
use App\Services\Invoicing\Efaktura\SapiSkClient;
use App\Support\Invoicing\Efaktura\PeppolParticipantId;
use App\Support\Invoicing\Efaktura\SapiSkSendPayload;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EpostmanOutboundTest extends TestCase
{
    private const ORIGIN = 'https://testpostman.slovakodata.com';

    private int $sendCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // Deliberately no RefreshDatabase outer transaction: reservations must
        // commit before HTTP. Each app uses its own disposable memory database.
        $this->artisan('migrate:fresh', ['--force' => true]);
        config(['efaktura.enabled' => true]);
        Cache::flush();
    }

    private function company(?User $user = null): Company
    {
        return Company::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'legal_name' => 'Synthetic issuer', 'jurisdiction' => 'eu_sk',
            'registration_number' => '12345678', 'tax_id' => '1234567890',
            'vat_number' => 'SK1234567890', 'country' => 'SK',
            'street' => 'Synthetic 1', 'city' => 'Bratislava', 'postal_code' => '81101',
            'default_currency' => 'EUR', 'vat_payer' => true, 'vat_status' => 'payer',
            'app_settings' => ['efaktura_enabled' => true, 'efaktura_auto_send' => true,
                'efaktura_sapi_base_url' => self::ORIGIN, 'efaktura_sapi_client_id' => 'synthetic-client',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-secret')],
        ]);
    }

    private function document(?Company $company = null, BusinessDocumentType $type = BusinessDocumentType::Invoice): BusinessDocument
    {
        $company ??= $this->company();
        $contact = CompanyContact::create(['company_id' => $company->id, 'name' => 'Synthetic customer', 'tax_id' => '9999999999', 'country' => 'SK']);
        $document = BusinessDocument::create([
            'company_id' => $company->id, 'company_contact_id' => $contact->id,
            'type' => $type, 'status' => BusinessDocumentStatus::Issued,
            'number' => 'SYNTHETIC-001', 'issue_date' => '2026-11-12', 'due_date' => '2026-11-26',
            'subtotal' => 10, 'tax_total' => 2.3, 'total' => 12.3, 'currency' => 'EUR',
        ]);
        BusinessDocumentLine::create(['business_document_id' => $document->id, 'sort_order' => 0, 'name' => 'Synthetic žľť test', 'quantity' => 1, 'unit' => 'ks', 'unit_price' => 10, 'tax_rate' => 23, 'line_total' => 12.3]);

        return $document->fresh(['company', 'contact', 'lines']);
    }

    private function fakeProvider(?callable $send = null): void
    {
        Http::fake(function ($request) use ($send) {
            if ($request->url() === self::ORIGIN.'/sapi/auth/token') {
                $this->assertSame('synthetic-client', $request['client_id']);
                $this->assertSame('synthetic-secret', $request['client_secret']);

                return Http::response(['access_token' => 'synthetic-token', 'token_type' => 'Bearer', 'expires_in' => 900]);
            }
            $this->assertSame(self::ORIGIN.'/sapi/document/send', $request->url());
            $this->sendCalls++;

            return $send ? $send($request) : Http::response(['providerDocumentId' => 'synthetic-provider-id', 'status' => 'ACCEPTED'], 202);
        });
    }

    public function test_accepted_is_processing_and_repeated_send_reuses_receipt_without_http(): void
    {
        $this->fakeProvider(function ($request) {
            $attempt = EfakturaOutboundAttempt::firstOrFail();
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame($attempt->id, $request->header('Idempotency-Key')[0]);
            $this->assertSame($request->data(), json_decode(Crypt::decryptString($attempt->request_encrypted), true));
            $this->assertStringContainsString('##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1', $request['metadata']['documentTypeId']);
            $this->assertSame('0245:1234567890', $request->header('X-Peppol-Participant-Id')[0]);

            return Http::response(['providerDocumentId' => 'synthetic-provider-id', 'status' => 'ACCEPTED'], 202);
        });
        $document = $this->document();
        $service = app(ComplianceSubmissionService::class);
        $result = $service->submitNow($document);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
        $this->assertSame('accepted', $result->responsePayload['epostman']['submission_state']);
        $this->assertFalse($result->responsePayload['epostman']['retry_allowed']);
        $this->assertFalse($result->responsePayload['epostman']['delivery_status_available']);
        $this->assertNull(EfakturaOutboundAttempt::firstOrFail()->request_encrypted);
        $binding = json_decode(Crypt::decryptString(EfakturaOutboundAttempt::firstOrFail()->status_binding_encrypted), true);
        $this->assertSame(self::ORIGIN, $binding['origin']);
        $this->assertSame(EfakturaOutboundAttempt::firstOrFail()->id, $binding['metadata']['documentId']);
        $this->assertSame('0245:9999999999', $binding['metadata']['receiverParticipantId']);
        $this->assertArrayNotHasKey('payload', $binding);
        $this->assertSame($result->externalId, $service->submitNow($document)->externalId);
        Http::assertSentCount(2);
        $row = $document->complianceSubmissions()->firstOrFail();
        app(ComplianceStatusSyncService::class)->refreshRow($row);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $row->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_lost_response_retries_exact_json_body_and_uuid(): void
    {
        $bodies = [];
        $keys = [];
        $this->fakeProvider(function ($request) use (&$bodies, &$keys) {
            $bodies[] = $request->body();
            $keys[] = $request->header('Idempotency-Key');
            if (count($bodies) === 1) {
                throw new ConnectionException('Synthetic timeout with sensitive data that must never escape');
            }

            return Http::response(['providerDocumentId' => 'synthetic-retry', 'status' => 'ACCEPTED'], 202);
        });
        $document = $this->document();
        $service = app(ComplianceSubmissionService::class);
        $unknown = $service->submitNow($document);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $unknown->status);
        $this->assertSame('uncertain', $unknown->responsePayload['epostman']['submission_state']);
        $this->assertTrue($unknown->responsePayload['epostman']['retry_allowed']);
        $attempt = EfakturaOutboundAttempt::firstOrFail();
        $this->assertStringNotContainsString('Synthetic žľť test', $attempt->request_encrypted);
        $this->assertStringNotContainsString('sensitive data', json_encode($unknown));
        $this->assertSame('synthetic-retry', $service->submitNow($document)->externalId);
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertDatabaseCount('efaktura_outbound_attempts', 1);
    }

    public function test_expired_unknown_is_retained_and_cannot_be_resent(): void
    {
        $this->fakeProvider(fn () => throw new ConnectionException('timeout'));
        $document = $this->document();
        $service = app(ComplianceSubmissionService::class);
        $service->submitNow($document);
        $attempt = EfakturaOutboundAttempt::firstOrFail();
        $this->travel(24)->hours();
        $result = $service->submitNow($document);
        $this->assertFalse($result->responsePayload['epostman']['retry_allowed']);
        $this->assertTrue($result->responsePayload['epostman']['reconciliation_required']);
        $this->assertSame($attempt->request_encrypted, $attempt->fresh()->request_encrypted);
        Http::assertSentCount(1);
        $this->assertSame(1, $this->sendCalls);
    }

    public function test_mutated_invoice_cannot_reuse_attempt_or_send_again(): void
    {
        $this->fakeProvider(fn () => throw new ConnectionException('timeout'));
        $document = $this->document();
        app(ComplianceSubmissionService::class)->submitNow($document);
        $document->lines->first()->update(['name' => 'Changed content']);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
            $this->fail('Changed request must be rejected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('efaktura', $exception->errors());
        }
        Http::assertSentCount(1);
        $this->assertSame(1, $this->sendCalls);
    }

    public function test_changed_connection_cannot_reuse_pending_attempt(): void
    {
        $this->fakeProvider(fn () => throw new ConnectionException('timeout'));
        $document = $this->document();
        app(ComplianceSubmissionService::class)->submitNow($document);
        $settings = $document->company->app_settings;
        $settings['efaktura_sapi_client_id'] = 'changed-client';
        $document->company->update(['app_settings' => $settings]);
        $this->expectException(ValidationException::class);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
        } finally {
            Http::assertSentCount(1);
            $this->assertSame(1, $this->sendCalls);
        }
    }

    public function test_disabled_connection_blocks_stale_document_before_http(): void
    {
        Http::fake();
        $document = $this->document();
        $settings = $document->company->app_settings;
        $settings['efaktura_enabled'] = false;
        Company::findOrFail($document->company_id)->update(['app_settings' => $settings]);
        $this->expectException(ValidationException::class);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_credit_note_uses_full_credit_note_identifier(): void
    {
        $this->fakeProvider(function ($request) {
            $this->assertStringStartsWith('urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##', $request['metadata']['documentTypeId']);
            $this->assertStringContainsString('<CreditNote ', $request['payload']);

            return Http::response(['providerDocumentId' => 'synthetic-credit', 'status' => 'ACCEPTED'], 202);
        });
        $result = app(ComplianceSubmissionService::class)->submitNow($this->document(type: BusinessDocumentType::CreditNote));
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
    }

    public function test_malformed_202_and_server_error_remain_uncertain(): void
    {
        $this->fakeProvider(fn () => Http::response(['status' => 'ACCEPTED'], 202));
        $document = $this->document();
        $result = app(ComplianceSubmissionService::class)->submitNow($document);
        $this->assertSame('uncertain', $result->responsePayload['epostman']['submission_state']);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
        $this->assertNotNull(EfakturaOutboundAttempt::firstOrFail()->request_encrypted);
    }

    public function test_422_is_sanitized_and_not_assumed_to_be_recipient_missing(): void
    {
        $this->fakeProvider(fn () => Http::response(['error' => 'VALIDATION_FAILED', 'detail' => 'SECRET synthetic invoice contents'], 422));
        $result = app(ComplianceSubmissionService::class)->submitNow($this->document());
        $this->assertSame(ComplianceSubmissionStatus::Failed, $result->status);
        $this->assertSame('rejected', $result->responsePayload['epostman']['submission_state']);
        $this->assertTrue($result->responsePayload['epostman']['retry_allowed']);
        $this->assertStringNotContainsString('SECRET', json_encode($result));
        $this->assertStringNotContainsString('not registered', (string) $result->message);
    }

    public function test_auto_send_setting_and_preexisting_job_do_not_send_epostman(): void
    {
        Http::fake();
        Bus::fake();
        $document = $this->document();
        app(ComplianceSubmissionService::class)->queueIfEligible($document);
        Bus::assertNotDispatched(SubmitBusinessDocumentCompliance::class);
        (new SubmitBusinessDocumentCompliance($document->id))->handle(app(ComplianceSubmissionService::class));
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    public function test_parent_transaction_is_rejected_without_http_or_reservation(): void
    {
        Http::fake();
        $document = $this->document();
        DB::beginTransaction();
        try {
            app(ComplianceSubmissionService::class)->submitNow($document);
            $this->fail('A parent transaction would defeat durable reservation');
        } catch (ValidationException) {
            Http::assertNothingSent();
            $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
        } finally {
            DB::rollBack();
        }
    }

    public function test_status_endpoint_cannot_be_configured_to_use_enterprise_with_sapi_credentials(): void
    {
        Http::fake();
        config(['efaktura.providers.sapi_sk.send_detail_path' => '/api/v1/invoices/{id}']);
        $this->assertNull(app(SapiSkClient::class)->sentDocumentDetailPathTemplate(self::ORIGIN));
        Http::assertNothingSent();
    }

    public function test_local_first_send_is_bound_to_explicit_company_and_keeps_no_document_row(): void
    {
        $this->fakeProvider();
        $user = $this->proUser();
        $company = $this->company($user);
        $response = $this->actingAs($user)->postJson('/api/invoicing/companies/'.$company->id.'/documents/ephemeral/efaktura/send', $this->localPayload($company));
        $response->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.response_payload.epostman.submission_state', 'accepted');
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseHas('ephemeral_efaktura_submissions', ['bridge_company_id' => $company->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('efaktura_outbound_attempts', ['company_id' => $company->id, 'source_kind' => 'local', 'source_document_id' => 'synthetic-local-id']);
        $this->actingAs($user)->postJson('/api/invoicing/companies/'.$company->id.'/documents/ephemeral/efaktura/send', $this->localPayload($company))->assertOk();
        Http::assertSentCount(2);
        $this->actingAs($user)->postJson('/api/invoicing/ephemeral/efaktura/refresh', ['evolu_document_id' => 'synthetic-local-id', 'bridge_company_id' => $company->id])->assertOk()->assertJsonPath('data.0.status', 'submitted');
        Http::assertSentCount(2);
    }

    public function test_explicit_alphanumeric_receiver_is_preserved_by_ubl_and_payload_builders(): void
    {
        $receiver = '9950:sk1234567890';
        Http::preventStrayRequests();
        $document = $this->document();
        $document->contact->update(['peppol_participant_id' => $receiver]);
        $document = $document->fresh(['company', 'contact', 'lines']);
        $resolved = PeppolParticipantId::fromContact($document->contact);
        $xml = app(BusinessDocumentUblService::class)->xml($document, auditDownload: false);
        $payload = SapiSkSendPayload::buildEpostman($document, '0245:1234567890', $resolved, $xml, $document->id);
        $this->assertSame($receiver, $payload['metadata']['receiverParticipantId']);
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $endpoint = '/*/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID';
        $this->assertSame('9950', $xpath->evaluate('string('.$endpoint.'/@schemeID)'));
        $this->assertSame('sk1234567890', $xpath->evaluate('string('.$endpoint.')'));
        $this->assertSame('0158', $xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/@schemeID)'));
        Http::assertNothingSent();
    }

    public static function invalidDomesticParticipants(): array
    {
        return [
            'VAT endpoint instead of DIC' => ['9950:sk9999999999', '9999999999', null],
            'endpoint DIC differs from buyer' => ['0245:8888888888', '9999999999', null],
            'buyer DIC missing' => ['0245:9999999999', null, null],
            'sender VAT endpoint' => [null, '9999999999', '9950:sk1234567890'],
            'sender endpoint differs from issuer' => [null, '9999999999', '0245:8888888888'],
        ];
    }

    #[DataProvider('invalidDomesticParticipants')]
    public function test_invalid_domestic_participants_are_rejected_before_provider_calls(?string $receiver, ?string $taxId, ?string $sender): void
    {
        $this->fakeProvider();
        $document = $this->document();
        $document->contact->update(['peppol_participant_id' => $receiver, 'tax_id' => $taxId]);
        if ($sender !== null) {
            $settings = $document->company->app_settings;
            $settings['efaktura_peppol_participant_id'] = $sender;
            $document->company->update(['app_settings' => $settings]);
        }
        $this->expectException(ValidationException::class);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
        } finally {
            Http::assertNothingSent();
            $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
        }
    }

    public function test_changed_buyer_dic_with_an_unchanged_explicit_endpoint_is_fenced_before_http(): void
    {
        $this->fakeProvider();
        $document = $this->document();
        $document->contact->update(['peppol_participant_id' => '0245:9999999999']);
        $contactId = $document->company_contact_id;
        $changed = false;
        Event::listen(TransactionCommitted::class, function () use ($contactId, &$changed): void {
            if (! $changed && EfakturaOutboundAttempt::count() === 1 && DB::transactionLevel() === 0) {
                $changed = true;
                DB::table('company_contacts')->where('id', $contactId)->update(['tax_id' => '8888888888']);
            }
        });
        $this->expectException(ValidationException::class);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
        } finally {
            $this->assertTrue($changed);
            Http::assertNothingSent();
            $this->assertDatabaseCount('efaktura_outbound_attempts', 1);
        }
    }

    public static function foreignDomesticParties(): array
    {
        return ['foreign issuer with SK jurisdiction' => ['sender'], 'foreign buyer with SK endpoint' => ['recipient']];
    }

    #[DataProvider('foreignDomesticParties')]
    public function test_domestic_service_rejects_foreign_party_countries_before_reservation(string $party): void
    {
        $this->fakeProvider();
        $document = $this->document();
        if ($party === 'sender') {
            $settings = $document->company->app_settings;
            $settings['efaktura_peppol_participant_id'] = '0245:1234567890';
            $document->company->update(['country' => 'CZ', 'app_settings' => $settings]);
        } else {
            $document->contact->update(['country' => 'CZ', 'peppol_participant_id' => '0245:9999999999']);
        }
        $this->expectException(ValidationException::class);
        try {
            app(EpostmanOutboundSubmissionService::class)->submit($document->fresh(['company', 'contact', 'lines']));
        } finally {
            Http::assertNothingSent();
            $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
        }
    }

    public function test_buyer_country_change_after_reservation_is_fenced_before_http(): void
    {
        $this->fakeProvider();
        $document = $this->document();
        $document->contact->update(['peppol_participant_id' => '0245:9999999999']);
        $contactId = $document->company_contact_id;
        $changed = false;
        Event::listen(TransactionCommitted::class, function () use ($contactId, &$changed): void {
            if (! $changed && EfakturaOutboundAttempt::count() === 1 && DB::transactionLevel() === 0) {
                $changed = true;
                DB::table('company_contacts')->where('id', $contactId)->update(['country' => 'CZ']);
            }
        });
        $this->expectException(ValidationException::class);
        try {
            app(EpostmanOutboundSubmissionService::class)->submit($document->fresh(['company', 'contact', 'lines']));
        } finally {
            $this->assertTrue($changed);
            Http::assertNothingSent();
            $this->assertDatabaseCount('efaktura_outbound_attempts', 1);
        }
    }

    public function test_local_first_issuer_spoof_and_foreign_company_are_rejected(): void
    {
        Http::fake();
        $user = $this->proUser();
        $company = $this->company($user);
        $payload = $this->localPayload($company);
        $payload['company']['tax_id'] = '9999999999';
        $this->actingAs($user)->postJson('/api/invoicing/companies/'.$company->id.'/documents/ephemeral/efaktura/send', $payload)->assertUnprocessable();
        $foreign = $this->company();
        $payload = $this->localPayload($foreign);
        $this->actingAs($user)->postJson('/api/invoicing/ephemeral/efaktura/send', $payload)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_companyless_send_uses_unique_matching_issuer_not_first_company(): void
    {
        $this->fakeProvider();
        $user = $this->proUser();
        $wrong = $this->company($user);
        $wrong->update(['tax_id' => '8888888888', 'vat_number' => 'SK8888888888']);
        $company = $this->company($user);
        $payload = $this->localPayload($company);
        unset($payload['bridge_company_id']);
        $this->actingAs($user)->postJson('/api/invoicing/ephemeral/efaktura/send', $payload)->assertOk();
        $this->assertDatabaseHas('efaktura_outbound_attempts', ['company_id' => $company->id]);
    }

    public function test_duplicate_matching_issuers_require_explicit_connection(): void
    {
        Http::fake();
        $user = $this->proUser();
        $company = $this->company($user);
        $this->company($user);
        $payload = $this->localPayload($company);
        unset($payload['bridge_company_id']);
        $this->actingAs($user)->postJson('/api/invoicing/ephemeral/efaktura/send', $payload)->assertUnprocessable()->assertJsonValidationErrors('bridge_company_id');
        Http::assertNothingSent();
    }

    public function test_snapshot_credentials_cannot_override_persisted_connection(): void
    {
        Http::fake();
        $user = $this->proUser();
        $company = $this->company($user);
        $payload = $this->localPayload($company);
        $payload['company']['app_settings'] = ['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_id' => 'foreign-client', 'efaktura_sapi_client_secret' => 'foreign-secret'];
        $this->actingAs($user)->postJson('/api/invoicing/companies/'.$company->id.'/documents/ephemeral/efaktura/send', $payload)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_rollback_cannot_discard_submission_evidence(): void
    {
        $this->fakeProvider();
        app(ComplianceSubmissionService::class)->submitNow($this->document());
        $migration = require database_path('migrations/2026_11_12_200000_create_efaktura_outbound_attempts_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_late_timeout_projection_cannot_overwrite_successful_retry(): void
    {
        $this->fakeProvider(function () {
            if ($this->sendCalls === 1) {
                throw new ConnectionException('lost first response');
            }

            return Http::response(['providerDocumentId' => 'synthetic-newer-success', 'status' => 'ACCEPTED'], 202);
        });
        $document = $this->document();
        $retried = false;
        Event::listen(TransactionCommitted::class, function () use ($document, &$retried): void {
            if (! $retried && $this->sendCalls === 1) {
                $retried = true;
                app(ComplianceSubmissionService::class)->submitNow($document);
            }
        });
        $result = app(ComplianceSubmissionService::class)->submitNow($document);
        $this->assertTrue($retried);
        $this->assertSame('synthetic-newer-success', $result->externalId);
        $this->assertSame('synthetic-newer-success', $document->complianceSubmissions()->firstOrFail()->external_id);
        $this->assertSame('accepted', $result->responsePayload['epostman']['submission_state']);
        $this->assertSame(2, $this->sendCalls);
    }

    public function test_server_error_is_uncertain_and_never_exposes_provider_payload(): void
    {
        $this->fakeProvider(fn () => Http::response(['detail' => 'SECRET internal error with invoice body'], 503));
        $result = app(ComplianceSubmissionService::class)->submitNow($this->document());
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
        $this->assertTrue($result->responsePayload['epostman']['retry_allowed']);
        $this->assertStringNotContainsString('SECRET', json_encode($result));
        $this->assertNotNull(EfakturaOutboundAttempt::firstOrFail()->request_encrypted);
    }

    public function test_explicit_provider_rejection_is_not_approval_or_safe_resend(): void
    {
        $this->fakeProvider(fn () => Http::response(['providerDocumentId' => 'synthetic-rejection', 'status' => 'REJECTED'], 202));
        $result = app(ComplianceSubmissionService::class)->submitNow($this->document());
        $this->assertSame(ComplianceSubmissionStatus::Rejected, $result->status);
        $this->assertFalse($result->responsePayload['epostman']['retry_allowed']);
        $this->assertNull(EfakturaOutboundAttempt::firstOrFail()->request_encrypted);
    }

    public function test_invoice_and_line_discounts_are_rejected_before_reservation_or_http(): void
    {
        Http::fake();
        foreach (['invoice', 'line'] as $discount) {
            $document = $this->document();
            if ($discount === 'invoice') {
                $document->update(['discount_percent' => 10]);
            } else {
                $document->lines->first()->update(['line_discount_percent' => 10]);
            }
            try {
                app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
                $this->fail('Discounts cannot be exported correctly yet');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('discounts', $exception->errors()['efaktura'][0]);
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    #[DataProvider('linePrecisionCases')]
    public function test_local_line_precision_loss_is_rejected_without_http_or_reservation(string $field, float $value): void
    {
        Http::fake();
        $user = $this->proUser();
        $company = $this->company($user);
        $payload = $this->localPayload($company);
        $payload['lines'][0]['quantity'] = 3;
        $payload['lines'][0][$field] = $value;
        $this->actingAs($user)->postJson('/api/invoicing/companies/'.$company->id.'/documents/ephemeral/efaktura/send', $payload)->assertUnprocessable()->assertJsonValidationErrors('efaktura');
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    /** @return array<string, array{string, float}> */
    public static function linePrecisionCases(): array
    {
        return ['unit price' => ['unit_price', 0.333333], 'quantity' => ['quantity', 0.333333], 'VAT rate' => ['tax_rate', 23.000001]];
    }

    public function test_wrong_stored_invoice_or_line_totals_cannot_be_silently_recalculated(): void
    {
        Http::fake();
        foreach (['document', 'line'] as $kind) {
            $document = $this->document();
            if ($kind === 'document') {
                $document->update(['total' => 99]);
            } else {
                $document->lines->first()->update(['line_total' => 99]);
            }
            try {
                app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
                $this->fail('The exported amount must match the issued invoice');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('amounts do not match', $exception->errors()['efaktura'][0]);
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    public function test_negative_paid_amount_and_overpayment_are_rejected_before_http(): void
    {
        Http::fake();
        foreach ([-1, 13] as $paid) {
            $document = $this->document();
            $document->update(['amount_paid' => $paid]);
            try {
                app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
                $this->fail('An invalid paid amount cannot be sent');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('paid amount', $exception->errors()['efaktura'][0]);
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    public function test_partial_payment_preserves_original_total_and_remaining_payable_amount(): void
    {
        $this->fakeProvider(function ($request) {
            $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="EUR">12.30</cbc:TaxInclusiveAmount>', $request['payload']);
            $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="EUR">4.00</cbc:PrepaidAmount>', $request['payload']);
            $this->assertStringContainsString('<cbc:PayableAmount currencyID="EUR">8.30</cbc:PayableAmount>', $request['payload']);

            return Http::response(['providerDocumentId' => 'synthetic-partial-payment', 'status' => 'ACCEPTED'], 202);
        });
        $document = $this->document();
        $document->update(['amount_paid' => 4]);
        $result = app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $result->status);
        $this->assertSame('12.30', $document->fresh()->total);
    }

    public function test_mixed_negative_unit_price_is_rejected_even_when_invoice_total_is_positive(): void
    {
        Http::fake();
        $document = $this->document();
        BusinessDocumentLine::create(['business_document_id' => $document->id, 'sort_order' => 1, 'name' => 'Synthetic deduction', 'quantity' => 1, 'unit_price' => -5, 'tax_rate' => 23, 'line_total' => -6.15]);
        $document->update(['subtotal' => 5, 'tax_total' => 1.15, 'total' => 6.15]);
        try {
            app(ComplianceSubmissionService::class)->submitNow($document->fresh(['company', 'contact', 'lines']));
            $this->fail('A negative UBL PriceAmount is unsupported');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('negative unit prices', $exception->errors()['efaktura'][0]);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
    }

    public function test_explicit_custom_connection_is_preserved_with_matching_epostman_company(): void
    {
        config(['efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900]),
            'https://sapi.test/sapi/v1/document/send' => Http::response(['providerDocumentId' => 'synthetic-custom', 'status' => 'ACCEPTED'], 202),
        ]);
        $user = $this->proUser();
        $custom = $this->company($user);
        $settings = $custom->app_settings;
        $settings['efaktura_sapi_base_url'] = 'https://sapi.test';
        $custom->update(['app_settings' => $settings, 'created_at' => now()->subDay()]);
        $this->company($user);
        $payload = $this->localPayload($custom);
        unset($payload['bridge_company_id']);
        $payload['company']['app_settings'] = [
            'efaktura_enabled' => true, 'efaktura_sapi_base_url' => 'https://sapi.test',
            'efaktura_sapi_client_id' => 'synthetic-client', 'efaktura_sapi_client_secret' => 'synthetic-secret',
        ];
        $this->actingAs($user)->postJson('/api/invoicing/ephemeral/efaktura/send', $payload)->assertOk()->assertJsonPath('data.external_id', 'synthetic-custom')->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseCount('efaktura_outbound_attempts', 0);
        Http::assertSentCount(2);
    }

    public function test_epostman_processing_rows_do_not_starve_custom_status_scheduler(): void
    {
        config(['efaktura.allowed_sapi_hosts' => ['sapi.test'], 'efaktura.providers.sapi_sk.send_detail_path' => '/sapi/v1/document/send/{id}']);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900]),
            'https://sapi.test/sapi/v1/document/send/custom-remote' => Http::response(['status' => 'DELIVERED']),
        ]);
        $company = $this->company();
        for ($index = 0; $index < 100; $index++) {
            $document = BusinessDocument::create(['company_id' => $company->id, 'type' => 'invoice', 'status' => 'issued', 'number' => 'SYNTHETIC-MARKED-'.$index, 'currency' => 'EUR']);
            BusinessDocumentCompliance::create(['business_document_id' => $document->id, 'provider' => 'peppol', 'status' => 'submitted', 'external_id' => 'marked-'.$index, 'submitted_at' => now()->subHour(), 'response_payload' => ['epostman' => ['submission_state' => 'accepted']]]);
        }
        $custom = $this->company();
        $settings = $custom->app_settings;
        $settings['efaktura_sapi_base_url'] = 'https://sapi.test';
        $custom->update(['app_settings' => $settings]);
        $document = $this->document($custom);
        $row = BusinessDocumentCompliance::create(['business_document_id' => $document->id, 'provider' => 'peppol', 'status' => 'submitted', 'external_id' => 'custom-remote', 'submitted_at' => now()->subMinutes(6), 'response_payload' => ['status' => 'PROCESSING']]);
        $stats = app(ComplianceStatusSyncService::class)->syncStaleSubmissions();
        $this->assertSame(['checked' => 1, 'updated' => 1], $stats);
        $this->assertSame(ComplianceSubmissionStatus::Approved, $row->fresh()->status);
        $this->assertSame(100, BusinessDocumentCompliance::query()->where('status', 'submitted')->count());
        Http::assertSentCount(2);
    }

    private function proUser(): User
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create(['code' => 'pro', 'name' => 'Pro', 'display_name' => 'Pro', 'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3, 'features' => ['business_invoicing'], 'is_active' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addYear()]);

        return $user;
    }

    private function localPayload(Company $company): array
    {
        return ['bridge_company_id' => $company->id, 'evolu_document_id' => 'synthetic-local-id',
            'company' => ['legal_name' => $company->legal_name, 'registration_number' => $company->registration_number, 'tax_id' => $company->tax_id, 'vat_number' => $company->vat_number, 'jurisdiction' => 'eu_sk', 'vat_payer' => true, 'vat_status' => 'payer', 'country' => 'SK', 'default_currency' => 'EUR'],
            'contact' => ['name' => 'Synthetic customer', 'tax_id' => '9999999999', 'country' => 'SK'],
            'document' => ['type' => 'invoice', 'status' => 'issued', 'number' => 'SYNTHETIC-LOCAL-001', 'issue_date' => '2026-11-12', 'due_date' => '2026-11-26', 'currency' => 'EUR'],
            'lines' => [['name' => 'Synthetic žľť line', 'quantity' => 1, 'unit' => 'ks', 'unit_price' => 10, 'tax_rate' => 23]],
        ];
    }
}
