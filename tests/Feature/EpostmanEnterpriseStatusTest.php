<?php

namespace Tests\Feature;

use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\EfakturaOutboundAttempt;
use App\Models\EphemeralEfakturaSubmission;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\Efaktura\EpostmanEnterpriseClient;
use App\Services\Invoicing\Efaktura\EpostmanOutboundSubmissionService;
use App\Services\Invoicing\Efaktura\EpostmanStatusSyncService;
use App\Services\Invoicing\EphemeralDocumentFactory;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\Efaktura\SapiSkSendPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EpostmanEnterpriseStatusTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://testpostman.slovakodata.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['efaktura.enabled' => true]);
        Http::preventStrayRequests();
    }

    private function company(): Company
    {
        return Company::create(['user_id' => User::factory()->create()->id, 'legal_name' => 'Synthetic',
            'jurisdiction' => 'eu_sk', 'country' => 'SK', 'tax_id' => '1234567890', 'vat_status' => 'payer',
            'vat_payer' => true, 'default_currency' => 'EUR', 'app_settings' => [
                'efaktura_enabled' => true, 'efaktura_sapi_base_url' => self::ORIGIN,
                'efaktura_sapi_client_id' => 'synthetic-client',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sapi-secret'),
                'efaktura_epostman_api_key_encrypted' => Crypt::encryptString('synthetic-enterprise-key'),
                'efaktura_epostman_status_generation' => (string) Str::uuid(),
            ]]);
    }

    private function fixture(string $kind = 'local', ?Company $company = null): array
    {
        $company ??= $this->company();
        $id = (string) Str::uuid();
        $source = (string) Str::uuid();
        $binding = ['documentId' => $id, 'senderParticipantId' => '0245:1234567890',
            'receiverParticipantId' => '0245:9384726109', 'processId' => SapiSkSendPayload::PROCESS_BILLING,
            'documentTypeId' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1'];
        $settings = CompanyEfakturaSettings::fromCompany($company);
        $payload = ['epostman' => ['submission_state' => 'accepted', 'retry_allowed' => false]];
        if ($kind === 'server') {
            $document = BusinessDocument::create(['id' => $source, 'company_id' => $company->id, 'number' => 'SYNTHETIC',
                'type' => 'invoice', 'status' => 'issued', 'issue_date' => '2026-11-12',
                'currency' => 'EUR', 'subtotal' => 1, 'tax_total' => 0, 'total' => 1]);
            $source = $document->id;
        }
        $attempt = EfakturaOutboundAttempt::create(['id' => $id, 'company_id' => $company->id, 'user_id' => $company->user_id,
            'source_kind' => $kind, 'source_document_id' => $source, 'connection_fingerprint' => $settings->connectionFingerprint(),
            'content_sha256' => str_repeat('a', 64), 'external_id' => (string) Str::uuid(), 'status' => 'submitted',
            'response_payload' => $payload, 'retry_until' => now()->addDay(),
            'status_binding_encrypted' => Crypt::encryptString(json_encode(['origin' => self::ORIGIN,
                'identity' => $settings->enterpriseIdentityFingerprint(), 'metadata' => $binding]))]);
        $common = ['provider' => 'peppol', 'status' => 'submitted', 'external_id' => $attempt->external_id,
            'response_payload' => $payload, 'submitted_at' => now()];
        $projection = $kind === 'server'
            ? BusinessDocumentCompliance::create(['business_document_id' => $source, ...$common])
            : EphemeralEfakturaSubmission::create(['user_id' => $company->user_id, 'bridge_company_id' => $company->id,
                'evolu_document_id' => $source, ...$common]);

        return [$company, $attempt, $projection, $binding];
    }

    private function response(EfakturaOutboundAttempt $attempt, array $binding, array $overrides = []): array
    {
        $data = array_fill_keys(['organizationId', 'invoiceUuid', 'direction', 'documentType', 's3Key',
            'retentionUntil', 'archiveStatus', 'createdAt', 'updatedAt'], 'synthetic');
        $data += array_fill_keys(['creationDateTime', 'acknowledgedAt', 'supplierName', 'supplierId', 'buyerName',
            'buyerId', 'invoiceNumber', 'issueDate', 'dueDate', 'totalAmount', 'currency', 'source'], null);

        return array_replace($data, ['id' => $attempt->external_id, 'clientDocumentId' => $attempt->id,
            'senderPeppolId' => $binding['senderParticipantId'], 'receiverPeppolId' => $binding['receiverParticipantId'],
            'documentTypeId' => $binding['documentTypeId'], 'processId' => $binding['processId'],
            'status' => 'UNCONFIRMED', 'mlsC3Status' => 'TIMEOUT', 'mlsC5Status' => 'OK'], $overrides);
    }

    private function fake(mixed $responses): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function pro(Company $company): void
    {
        $plan = SubscriptionPlan::create(['code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 1, 'billing_period' => 'year', 'features' => ['business_invoicing'], 'is_active' => true]);
        Subscription::create(['user_id' => $company->user_id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear()]);
        $this->actingAs(User::findOrFail($company->user_id));
    }

    public function test_both_sources_refresh_atomically_without_approving_or_resending(): void
    {
        foreach (['server', 'local'] as $kind) {
            [$company, $attempt, $projection, $binding] = $this->fixture($kind);
            $this->fake([self::ORIGIN.'/api/v1/invoices/'.$attempt->external_id => Http::response($this->response($attempt, $binding))]);
            $this->assertTrue(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
            $attempt->refresh();
            $projection->refresh();
            $status = $attempt->response_payload['epostman']['enterprise_status'];
            $this->assertSame('UNCONFIRMED', $status['status']);
            $this->assertSame('TIMEOUT', $status['mls_c3_status']);
            $this->assertSame('OK', $status['mls_c5_status']);
            $this->assertSame(ComplianceSubmissionStatus::Submitted, $projection->status);
            $this->assertNull($projection->resolved_at);
            $this->assertSame($status, $projection->response_payload['epostman']['enterprise_status']);
            $this->assertFalse($attempt->status_complete);
            $this->assertNotNull($attempt->status_next_check_at);
            $this->assertStringNotContainsString('synthetic-enterprise-key', json_encode($projection));
            Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Api-Key', 'synthetic-enterprise-key') && ! $r->hasHeader('Authorization'));
        }
    }

    public function test_manual_refresh_and_scheduled_due_times_enforce_cooldown_and_backoff(): void
    {
        [, $attempt, , $binding] = $this->fixture();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding))]);
        $service = app(EpostmanStatusSyncService::class);
        $this->assertTrue($service->refreshAttempt($attempt));
        $this->assertFalse($service->refreshAttempt($attempt));
        $this->assertSame(0, $service->syncDue()['checked']);
        $this->travel(61)->seconds();
        $this->fake([self::ORIGIN.'/*' => Http::response(['message' => 'sensitive-sentinel'], 429)]);
        $this->assertTrue($service->refreshAttempt($attempt));
        $attempt->refresh();
        $status = $attempt->response_payload['epostman']['enterprise_status'];
        $this->assertSame('UNCONFIRMED', $status['status']);
        $this->assertSame('rate_limited', $status['error_code']);
        $this->assertSame(1, $attempt->status_failures);
        $this->assertTrue($attempt->status_next_check_at->gt(now()->addMinutes(5)));
        $this->assertStringNotContainsString('sensitive-sentinel', json_encode($attempt));
    }

    public function test_missing_binding_never_reads_provider_or_fabricates_legacy_metadata(): void
    {
        [, $attempt, $projection] = $this->fixture();
        $attempt->update(['status_binding_encrypted' => null]);
        $this->assertTrue(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
        $this->assertSame('binding_unavailable', $projection->fresh()->response_payload['epostman']['enterprise_status']['error_code']);
        Http::assertNothingSent();
    }

    public function test_generation_change_during_get_discards_the_response(): void
    {
        [$company, $attempt, $projection, $binding] = $this->fixture();
        $this->fake(function () use ($company, $attempt, $binding) {
            $company->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($company->app_settings,
                ['efaktura_epostman_api_key' => 'synthetic-new-key'])]);

            return Http::response($this->response($attempt, $binding));
        });
        $this->assertFalse(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
        $this->assertArrayNotHasKey('enterprise_status', $projection->fresh()->response_payload['epostman']);
        $this->assertNull($attempt->fresh()->status_last_attempt_at);
    }

    public function test_disabled_foreign_owner_and_changed_projection_never_update_status(): void
    {
        [$company, $attempt, $projection] = $this->fixture();
        $company->update(['app_settings' => array_merge($company->app_settings, ['efaktura_enabled' => false])]);
        $this->assertFalse(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
        $company->update(['user_id' => User::factory()->create()->id]);
        $this->assertFalse(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
        Http::assertNothingSent();
    }

    public function test_response_mismatches_malformed_json_unknown_tokens_and_errors_are_safe(): void
    {
        [, $attempt, , $binding] = $this->fixture();
        $client = app(EpostmanEnterpriseClient::class);
        foreach (['id', 'clientDocumentId', 'senderPeppolId', 'receiverPeppolId', 'documentTypeId', 'processId'] as $field) {
            $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding, [$field => 'wrong']))]);
            $this->assertSame('identity_mismatch', $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding)['error_code']);
        }
        foreach (['[]', 'null', '{', json_encode(['data' => $this->response($attempt, $binding)])] as $body) {
            $this->fake([self::ORIGIN.'/*' => Http::response($body)]);
            $this->assertSame('response_invalid', $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding)['error_code']);
        }
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'private-status-sentinel', 'mlsC3Status' => null, 'mlsC5Status' => 'private-mls-sentinel']))]);
        $this->assertSame(['status' => 'OTHER', 'mls_c3_status' => null, 'mls_c5_status' => 'OTHER'], $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding)['observation']);
        $this->fake(fn () => throw new ConnectionException('private-error-sentinel'));
        $this->assertSame(['observation' => null, 'error_code' => 'network_error'], $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding));
    }

    public function test_enterprise_never_sends_headers_to_custom_urls_or_arbitrary_identifiers(): void
    {
        $client = app(EpostmanEnterpriseClient::class);
        foreach (['https://custom.example', self::ORIGIN.'/wrong', self::ORIGIN.':444', 'http://testpostman.slovakodata.com'] as $origin) {
            $this->assertSame('configuration_error', $client->invoice($origin, 'synthetic-key', (string) Str::uuid(), [])['error_code']);
        }
        foreach (['../x', 'x?y=1', 'not-a-uuid'] as $id) {
            $this->assertSame('configuration_error', $client->invoice(self::ORIGIN, 'synthetic-key', $id, [])['error_code']);
        }
        Http::assertNothingSent();
    }

    public function test_delivered_with_unresolved_tax_keeps_polling_but_complete_observations_stop(): void
    {
        [, $attempt, , $binding] = $this->fixture();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'DELIVERED', 'mlsC3Status' => 'OK', 'mlsC5Status' => null]))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertFalse($attempt->fresh()->status_complete);
        $this->travel(61)->seconds();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'DELIVERED', 'mlsC3Status' => 'OK', 'mlsC5Status' => 'OK']))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertTrue($attempt->fresh()->status_complete);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_scheduler_rotates_companies_and_runs_without_generic_detail_path(): void
    {
        config(['efaktura.status_company_limit' => 1, 'efaktura.status_per_company_limit' => 1, 'efaktura.providers.sapi_sk.send_detail_path' => '']);
        [$first] = $this->fixture();
        $this->fixture(company: $first);
        $this->fixture();
        $this->fake([self::ORIGIN.'/*' => Http::response([], 401)]);
        $this->artisan('efaktura:sync-epostman-status')->assertSuccessful();
        $this->artisan('efaktura:sync-epostman-status')->assertSuccessful();
        $this->assertSame(2, EfakturaOutboundAttempt::whereNotNull('status_last_attempt_at')->distinct()->count('company_id'));
        Http::assertSentCount(2);
    }

    public function test_late_send_projection_uses_the_canonical_new_status(): void
    {
        [, $attempt, $projection, $binding] = $this->fixture('server');
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding))]);
        $service = app(EpostmanOutboundSubmissionService::class);
        $stale = $service->result($attempt);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $document = $projection->document()->with('company')->firstOrFail();
        $service->withLatestResult($document, $stale, fn ($result) => $projection->update(['response_payload' => $result->responsePayload]));
        $this->assertSame('TIMEOUT', $projection->fresh()->response_payload['epostman']['enterprise_status']['mls_c3_status']);
    }

    public function test_write_only_settings_preserve_send_fingerprint_and_invalidate_only_identity_changes(): void
    {
        $company = $this->company();
        $this->pro($company);
        $url = '/api/invoicing/companies/'.$company->id.'/app-settings';
        $before = CompanyEfakturaSettings::fromCompany($company);
        $response = $this->patchJson($url, ['efaktura_epostman_api_key' => 'synthetic-new-key'])->assertOk();
        $response->assertJsonPath('data.app_settings.efaktura_epostman_api_key_set', true);
        $this->assertStringNotContainsString('synthetic-new-key', $response->getContent());
        $this->assertStringNotContainsString('efaktura_epostman_api_key_encrypted', $response->getContent());
        $this->assertStringNotContainsString('efaktura_epostman_status_generation', $response->getContent());
        $after = CompanyEfakturaSettings::fromCompany($company->fresh());
        $this->assertSame($before->connectionFingerprint(), $after->connectionFingerprint());
        $this->assertNotSame($before->enterpriseStatusFingerprint(), $after->enterpriseStatusFingerprint());
        $this->patchJson($url, ['efaktura_sapi_client_id' => 'replacement-client', 'efaktura_inbound_enabled' => true])->assertOk();
        $this->assertSame('synthetic-new-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
        $this->patchJson($url, ['efaktura_sapi_base_url' => 'https://TESTPOSTMAN.slovakodata.com:443/'])->assertOk();
        $this->assertSame('synthetic-new-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
        $this->patchJson($url, ['efaktura_peppol_participant_id' => '0245:1234567890'])->assertOk();
        $this->assertSame('synthetic-new-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
        $this->patchJson($url, ['efaktura_sapi_base_url' => 'https://postman.slovakodata.com'])->assertOk();
        $this->assertNull(CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
    }

    public function test_invalid_internal_and_snapshot_credentials_are_rejected_without_clearing_saved_key(): void
    {
        $company = $this->company();
        $this->pro($company);
        $url = '/api/invoicing/companies/'.$company->id.'/app-settings';
        foreach (['', ' ', "invalid\nkey", 'key with spaces', str_repeat('x', 4097)] as $key) {
            $this->patchJson($url, ['efaktura_epostman_api_key' => $key])->assertUnprocessable();
        }
        foreach (['efaktura_epostman_api_key_encrypted', 'efaktura_epostman_status_generation', 'efaktura_epostman_api_key_set'] as $field) {
            $this->patchJson($url, [$field => 'forged'])->assertUnprocessable();
        }
        $this->assertSame('synthetic-enterprise-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
        $snapshot = app(EphemeralDocumentFactory::class)->snapshotCompany($company, ['app_settings' => [
            'efaktura_epostman_api_key' => 'forged', 'efaktura_epostman_api_key_set' => true,
            'efaktura_epostman_api_key_encrypted' => 'forged']]);
        foreach (CompanyEfakturaSettings::ENTERPRISE_PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $snapshot->app_settings);
        }
        $this->assertArrayNotHasKey('efaktura_epostman_api_key_set', $snapshot->app_settings);
        $this->patchJson($url, ['efaktura_epostman_api_key' => null])->assertOk()->assertJsonPath('data.app_settings.efaktura_epostman_api_key_set', false);
    }

    public function test_profile_identity_change_clears_status_key(): void
    {
        $company = $this->company();
        $this->pro($company);
        $this->patchJson('/api/invoicing/companies/'.$company->id, ['tax_id' => '9999900009'])->assertOk()
            ->assertJsonPath('data.app_settings.efaktura_epostman_api_key_set', false);
    }

    public function test_multipart_key_input_cannot_clear_the_saved_key_after_middleware_normalization(): void
    {
        $company = $this->company();
        $this->pro($company);
        $this->patch('/api/invoicing/companies/'.$company->id.'/app-settings', ['efaktura_epostman_api_key' => ''],
            ['Content-Type' => 'multipart/form-data', 'Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame('synthetic-enterprise-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
    }

    public function test_accepted_member_cannot_write_or_remove_the_enterprise_key(): void
    {
        $company = $this->company();
        $this->pro($company);
        $member = User::factory()->create();
        CompanyMember::create(['company_id' => $company->id, 'user_id' => $member->id,
            'role' => 'accountant', 'invited_by' => $company->user_id, 'accepted_at' => now()]);
        $this->actingAs($member)->patchJson('/api/invoicing/companies/'.$company->id.'/app-settings',
            ['efaktura_epostman_api_key' => null])->assertForbidden();
        $this->assertSame('synthetic-enterprise-key', CompanyEfakturaSettings::fromCompany($company->fresh())->enterpriseApiKey());
    }

    public function test_all_finite_statuses_and_mls_values_are_observations_without_approval(): void
    {
        [, $attempt, , $binding] = $this->fixture();
        $client = app(EpostmanEnterpriseClient::class);
        foreach (EpostmanEnterpriseClient::STATUSES as $status) {
            $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding, ['status' => $status]))]);
            $this->assertSame($status, $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding)['observation']['status']);
        }
        foreach (EpostmanEnterpriseClient::MLS as $mls) {
            $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
                ['mlsC3Status' => $mls, 'mlsC5Status' => $mls]))]);
            $observation = $client->invoice(self::ORIGIN, 'synthetic-key', $attempt->external_id, $binding)['observation'];
            $this->assertSame($mls, $observation['mls_c3_status']);
            $this->assertSame($mls, $observation['mls_c5_status']);
        }
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_projection_change_during_get_discards_the_observation(): void
    {
        [, $attempt, $projection, $binding] = $this->fixture();
        $this->fake(function () use ($projection, $attempt, $binding) {
            $projection->update(['external_id' => (string) Str::uuid()]);

            return Http::response($this->response($attempt, $binding));
        });
        $this->assertFalse(app(EpostmanStatusSyncService::class)->refreshAttempt($attempt));
        $this->assertNull($attempt->fresh()->status_last_attempt_at);
        $this->assertArrayNotHasKey('enterprise_status', $projection->fresh()->response_payload['epostman']);
    }

    public function test_manual_server_and_both_local_routes_need_only_enterprise_credentials(): void
    {
        $company = $this->company();
        $this->pro($company);
        $settings = $company->app_settings;
        unset($settings['efaktura_sapi_client_secret_encrypted'], $settings['efaktura_sapi_client_id']);
        $company->update(['app_settings' => $settings]);
        foreach (['server', 'local-company', 'local-user'] as $route) {
            [, $attempt, $projection, $binding] = $this->fixture($route === 'server' ? 'server' : 'local', $company);
            $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding))]);
            $prefix = '/api/invoicing/companies/'.$company->id;
            $url = match ($route) {
                'server' => $prefix.'/documents/'.$attempt->source_document_id.'/efaktura/compliance/refresh',
                'local-company' => $prefix.'/documents/ephemeral/efaktura/refresh',
                default => '/api/invoicing/ephemeral/efaktura/refresh',
            };
            $this->postJson($url, ['evolu_document_id' => $attempt->source_document_id, 'bridge_company_id' => $company->id])
                ->assertOk()->assertJsonPath('data.0.response_payload.epostman.enterprise_status.mls_c3_status', 'TIMEOUT');
            Http::assertSentCount(1);
        }
    }

    public function test_accepted_observation_requires_both_positive_mls_to_stop_polling(): void
    {
        [, $attempt, , $binding] = $this->fixture();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'ACCEPTED', 'mlsC3Status' => 'OK', 'mlsC5Status' => null]))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertFalse($attempt->fresh()->status_complete);
        $this->travel(61)->seconds();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'ACCEPTED', 'mlsC3Status' => 'OK', 'mlsC5Status' => 'OK']))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertTrue($attempt->fresh()->status_complete);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_non_peppol_completion_uses_tax_receipt_without_inventing_recipient_mls(): void
    {
        [, $attempt, $projection, $binding] = $this->fixture();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'DELIVERED_NON_PEPPOL', 'mlsC3Status' => null, 'mlsC5Status' => null]))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertFalse($attempt->fresh()->status_complete);
        $this->assertNotNull($attempt->fresh()->status_next_check_at);
        $this->assertNull($projection->fresh()->response_payload['epostman']['enterprise_status']['mls_c3_status']);

        $this->travel(61)->seconds();
        $this->fake([self::ORIGIN.'/*' => Http::response($this->response($attempt, $binding,
            ['status' => 'DELIVERED_NON_PEPPOL', 'mlsC3Status' => null, 'mlsC5Status' => 'OK']))]);
        app(EpostmanStatusSyncService::class)->refreshAttempt($attempt);
        $this->assertTrue($attempt->fresh()->status_complete);
        $this->assertNull($attempt->fresh()->status_next_check_at);
        $this->assertNull($projection->fresh()->response_payload['epostman']['enterprise_status']['mls_c3_status']);
        $this->assertSame('OK', $projection->fresh()->response_payload['epostman']['enterprise_status']['mls_c5_status']);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $projection->fresh()->status);
        $this->assertNull($projection->fresh()->resolved_at);
        $this->assertSame(ComplianceSubmissionStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_key_setup_pins_implicit_origin_without_changing_send_fingerprint(): void
    {
        config(['efaktura.providers.sapi_sk.base_url' => self::ORIGIN]);
        $company = $this->company();
        $current = $company->app_settings;
        unset($current['efaktura_sapi_base_url']);
        $company->update(['app_settings' => $current]);
        $before = CompanyEfakturaSettings::fromCompany($company)->connectionFingerprint();
        $company->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_epostman_api_key' => 'synthetic-replacement-key'])]);
        $this->assertSame($before, CompanyEfakturaSettings::fromCompany($company)->connectionFingerprint());
        config(['efaktura.providers.sapi_sk.base_url' => 'https://postman.slovakodata.com']);
        $this->assertSame(self::ORIGIN, CompanyEfakturaSettings::fromCompany($company)->enterpriseOrigin());
        $this->assertSame('synthetic-replacement-key', CompanyEfakturaSettings::fromCompany($company)->enterpriseApiKey());
    }

    public function test_status_migration_can_rollback_and_reapply_without_losing_custody_or_schedule(): void
    {
        [, $attempt] = $this->fixture();
        $attempt->update(['request_encrypted' => Crypt::encryptString('synthetic retained retry request'),
            'status_last_attempt_at' => now(), 'status_next_check_at' => now()->addHour(), 'status_failures' => 3]);
        $fields = ['status_binding_encrypted', 'request_encrypted', 'status_last_attempt_at', 'status_next_check_at',
            'status_failures', 'status_complete', 'external_id', 'connection_fingerprint', 'content_sha256'];
        $retainedValues = static fn () => array_intersect_key($attempt->fresh()->getRawOriginal(), array_flip($fields));
        $retained = $retainedValues();
        $name = '2026_11_12_220000_add_status_tracking_to_efaktura_outbound_attempts';
        $path = 'database/migrations/'.$name.'.php';
        DB::table('migrations')->where('migration', $name)->update(['batch' => DB::table('migrations')->max('batch') + 1]);

        $this->artisan('migrate:rollback', ['--path' => [$path], '--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertDatabaseMissing('migrations', ['migration' => $name]);
        $this->assertSame($retained, $retainedValues());
        $this->assertTrue(Schema::hasIndex('efaktura_outbound_attempts', 'efaktura_status_due_index'));

        // Older code writes only its existing columns while additive data stays.
        DB::table('efaktura_outbound_attempts')->where('id', $attempt->id)->update(['message' => 'Synthetic legacy-version write']);
        $this->artisan('migrate', ['--path' => [$path], '--force' => true])->assertSuccessful();
        $this->assertDatabaseHas('migrations', ['migration' => $name]);
        $this->assertSame($retained, $retainedValues());
        $this->assertSame('Synthetic legacy-version write', $attempt->fresh()->message);
        $this->assertTrue(Schema::hasIndex('efaktura_outbound_attempts', 'efaktura_status_due_index'));

        // A retry after interrupted migration/index creation is safe too.
        Schema::table('efaktura_outbound_attempts', fn ($table) => $table->dropIndex('efaktura_status_due_index'));
        $migration = require database_path('migrations/'.$name.'.php');
        $migration->up();
        $migration->up();
        $this->assertSame($retained, $retainedValues());
        $this->assertTrue(Schema::hasIndex('efaktura_outbound_attempts', 'efaktura_status_due_index'));
    }
}
