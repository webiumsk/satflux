<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Models\User;
use App\Services\Invoicing\Efaktura\EfakturaInboundInboxService;
use App\Services\Invoicing\Efaktura\EfakturaInboundService;
use App\Services\Invoicing\Efaktura\SapiSkClient;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EpostmanInboundTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://testpostman.slovakodata.com';

    private const PARTICIPANT = '0245:1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['efaktura.enabled' => true, 'invoicing.local_first' => true]);
        Http::preventStrayRequests();
    }

    private function company(string $origin = self::ORIGIN): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic receiver',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'tax_id' => '1234567890',
            'country' => 'SK',
            'vat_status' => 'none',
            'vat_payer' => false,
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_inbound_enabled' => true,
                'efaktura_sapi_base_url' => $origin,
                'efaktura_sapi_client_id' => 'synthetic-client',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-secret'),
            ],
        ]);
    }

    private function xml(string $receiver = '1234567890', bool $credit = false): string
    {
        $type = $credit ? 'CreditNote' : 'Invoice';

        return '<'.$type.' xmlns="urn:oasis:names:specification:ubl:schema:xsd:'.$type.'-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>SYNTHETIC-001</cbc:ID><cbc:IssueDate>2026-11-10</cbc:IssueDate><cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode><cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Synthetic supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty><cac:AccountingCustomerParty><cac:Party><cbc:EndpointID schemeID="0245">'.$receiver.'</cbc:EndpointID></cac:Party></cac:AccountingCustomerParty><cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="EUR">42.50</cbc:PayableAmount></cac:LegalMonetaryTotal></'.$type.'>';
    }

    private function metadata(string $id): array
    {
        return ['documentId' => $id, 'documentTypeId' => 'synthetic-type', 'processId' => 'synthetic-process', 'senderParticipantId' => '0245:9999999999', 'receiverParticipantId' => self::PARTICIPANT, 'creationDateTime' => '2026-11-10T12:00:00Z'];
    }

    private function fakeProvider(?string $xml = null, bool $twoPages = false, bool $expired = false, bool $ackFails = false): void
    {
        $xml ??= $this->xml();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($xml, $twoPages, $expired, $ackFails) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                $this->assertStringContainsString('application/json', $request->header('Content-Type')[0]);
                $this->assertSame('client_credentials', $request['grant_type']);

                return Http::response(['access_token' => $expired && $request['client_id'] ? 'fresh-token' : 'synthetic-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null]);
            }
            $this->assertSame([self::PARTICIPANT], $request->header('X-Peppol-Participant-Id'));
            if ($expired && $request->header('Authorization') === ['Bearer stale-token']) {
                return Http::response(['error' => 'expired'], 401);
            }
            if (str_contains($request->url(), '/acknowledge')) {
                $id = str_contains($request->url(), 'doc-2') ? 'doc-2' : 'doc-1';
                $receipt = EfakturaInboundReceipt::where('external_document_id', $id)->firstOrFail();
                $this->assertSame($xml, Crypt::decryptString($receipt->ubl_encrypted), 'Original custody precedes ACK');
                $this->assertSame(hash('sha256', $xml), $receipt->original_sha256);

                return $ackFails ? Http::response(['error' => 'retry later'], 503) : Http::response(['documentId' => $id, 'status' => 'ACKNOWLEDGED', 'acknowledgedDateTime' => '2026-11-10T12:00:00Z']);
            }
            if (preg_match('~/receive/(doc-[12])$~', $request->url(), $matches)) {
                return Http::response(['metadata' => $this->metadata($matches[1]), 'payload' => $xml, 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }
            $second = str_contains($request->url(), 'pageToken=next');
            $list = ['documents' => [array_merge($this->metadata($second ? 'doc-2' : 'doc-1'), ['status' => 'RECEIVED'])], 'totalCount' => $twoPages ? 2 : 1];
            if ($twoPages && ! $second) {
                $list['nextPageToken'] = 'next';
            }

            return Http::response($list);
        });
    }

    public function test_pages_are_received_and_encrypted_without_an_open_browser_and_replays_deduplicate(): void
    {
        $this->fakeProvider(twoPages: true);
        $company = $this->company();
        $stats = app(EfakturaInboundService::class)->pollAll();
        $this->assertSame(2, $stats['acknowledged']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 2);
        $this->assertDatabaseCount('business_expenses', 0);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $this->assertSame('pending', $receipt->inbox_status);
        $this->assertSame(self::ORIGIN, $receipt->connection_origin);
        $this->assertSame(self::PARTICIPANT, $receipt->receiver_participant_id);
        $this->assertStringNotContainsString('<Invoice', $receipt->ubl_encrypted);
        $this->assertStringNotContainsString('<Invoice', json_encode($receipt->response_payload));
        $again = app(EfakturaInboundService::class)->pollCompany($company->fresh());
        $this->assertSame(2, $again['skipped']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 2);
    }

    public function test_401_refreshes_cached_access_once_and_retries_safe_receive(): void
    {
        $this->fakeProvider(expired: true);
        $company = $this->company();
        Cache::put('efaktura.sapi_sk.token.'.hash('sha256', 'synthetic-client|synthetic-secret|'.self::ORIGIN), 'stale-token', 600);
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company)['acknowledged']);
        Http::assertSentCount(5);
    }

    public function test_failed_ack_keeps_original_and_retries_without_creating_another_receipt(): void
    {
        $company = $this->company();
        $this->fakeProvider(ackFails: true);
        $this->assertSame(0, app(EfakturaInboundService::class)->pollCompany($company)['acknowledged']);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $this->assertNull($receipt->acknowledged_at);
        $this->fakeProvider();
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 1);
    }

    public function test_unreadable_or_invalid_staged_original_is_recovered_before_acknowledging(): void
    {
        $company = $this->company();
        foreach (['corrupt-ciphertext', Crypt::encryptString('<not-ubl/>'), Crypt::encryptString($this->xml('0000000000'))] as $corrupt) {
            $this->fakeProvider(ackFails: true);
            app(EfakturaInboundService::class)->pollCompany($company->fresh());
            $receipt = EfakturaInboundReceipt::firstOrFail();
            $id = $receipt->evolu_expense_id;
            $receipt->update(['ubl_encrypted' => $corrupt]);
            $this->fakeProvider();
            $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
            Http::assertSent(fn ($request) => str_ends_with($request->url(), '/receive/doc-1'));
            $this->assertSame($id, $receipt->fresh()->evolu_expense_id);
            $receipt = $receipt->fresh();
            $receipt->update(['acknowledged_at' => null]);
        }
        $this->assertDatabaseCount('efaktura_inbound_receipts', 1);
    }

    public function test_a_completed_local_handoff_without_server_bytes_is_recovered_before_ack_retry(): void
    {
        $company = $this->company();
        $this->fakeProvider(ackFails: true);
        app(EfakturaInboundService::class)->pollCompany($company);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $localId = $receipt->evolu_expense_id;
        $service = app(EfakturaInboundInboxService::class);
        $digest = hash('sha256', $this->xml());
        $token = $service->prepareImport($receipt, $company, $digest);
        $service->markImported($receipt, $company, $digest, $token);
        $this->assertNull($receipt->fresh()->ubl_encrypted);
        $this->fakeProvider();
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/receive/doc-1'));
        $this->assertSame($localId, $receipt->fresh()->evolu_expense_id);
        $this->assertSame($this->xml(), Crypt::decryptString($receipt->fresh()->ubl_encrypted));
    }

    public function test_recovery_cannot_replace_an_established_original_with_changed_provider_bytes(): void
    {
        $company = $this->company();
        $this->fakeProvider(ackFails: true);
        app(EfakturaInboundService::class)->pollCompany($company);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $receipt->update(['ubl_encrypted' => 'synthetic-unreadable-ciphertext']);
        $before = $receipt->fresh()->getAttributes();

        $changedXml = str_replace('SYNTHETIC-001', 'SYNTHETIC-CHANGED', $this->xml());
        $this->fakeProvider($changedXml);
        $stats = app(EfakturaInboundService::class)->pollCompany($company->fresh());
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, $stats['acknowledged']);
        $this->assertGreaterThan(0, $stats['failed']);
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/acknowledge'));

        $this->fakeProvider();
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
        $this->assertSame(hash('sha256', $this->xml()), $receipt->fresh()->original_sha256);
        $this->assertSame($this->xml(), Crypt::decryptString($receipt->fresh()->ubl_encrypted));
    }

    public function test_tax_identity_change_invalidates_an_inflight_poll_with_an_unchanged_explicit_participant(): void
    {
        $company = $this->company();
        $company->update(['app_settings' => array_merge($company->app_settings, [
            'efaktura_peppol_participant_id' => self::PARTICIPANT,
        ])]);
        $before = CompanyEfakturaSettings::fromCompany($company);
        Http::fake(function ($request) use ($company) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/receive/doc-1')) {
                $company->fresh()->update(['tax_id' => '0987654321']);

                return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => $this->xml(), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                return Http::response(['documentId' => 'doc-1', 'status' => 'ACKNOWLEDGED']);
            }

            return Http::response(['documents' => [$this->metadata('doc-1')]]);
        });

        $stats = app(EfakturaInboundService::class)->pollCompany($company);
        $after = CompanyEfakturaSettings::fromCompany($company->fresh());
        $this->assertSame($before->peppolParticipantId(), $after->peppolParticipantId());
        $this->assertNotSame($before->connectionFingerprint(), $after->connectionFingerprint());
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, $stats['acknowledged']);
        $this->assertGreaterThan(0, $stats['failed']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 0);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/acknowledge'));
    }

    public function test_recovery_respects_an_original_digest_pinned_while_detail_is_pending(): void
    {
        $company = $this->company();
        $this->fakeProvider(ackFails: true);
        app(EfakturaInboundService::class)->pollCompany($company);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $receipt->update(['ubl_encrypted' => 'synthetic-unreadable-ciphertext', 'original_sha256' => null]);
        $pinned = null;
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($receipt, &$pinned) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/receive/doc-1')) {
                $receipt->fresh()->update(['original_sha256' => hash('sha256', $this->xml())]);
                $pinned = $receipt->fresh()->getAttributes();

                return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => str_replace('SYNTHETIC-001', 'SYNTHETIC-CHANGED', $this->xml()), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                return Http::response(['documentId' => 'doc-1', 'status' => 'ACKNOWLEDGED']);
            }

            return Http::response(['documents' => [$this->metadata('doc-1')]]);
        });

        $stats = app(EfakturaInboundService::class)->pollCompany($company->fresh());
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, $stats['acknowledged']);
        $this->assertGreaterThan(0, $stats['failed']);
        $this->assertNotNull($pinned);
        $this->assertSame($pinned, $receipt->fresh()->getAttributes());
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/acknowledge'));
    }

    public function test_missing_server_original_is_recovered_without_duplicating_its_expense(): void
    {
        config(['invoicing.local_first' => false]);
        Storage::fake('local');
        $company = $this->company();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                return Http::response(['error' => 'retry'], 503);
            }
            if (str_ends_with($request->url(), '/receive/doc-1')) {
                return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => $this->xml(), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }

            return Http::response(['documents' => [$this->metadata('doc-1')]]);
        });
        app(EfakturaInboundService::class)->pollCompany($company);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $expenseId = $receipt->business_expense_id;
        Storage::disk('local')->delete($receipt->attachment_path);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                return Http::response(['documentId' => 'doc-1', 'status' => 'ACKNOWLEDGED']);
            }
            if (str_ends_with($request->url(), '/receive/doc-1')) {
                return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => $this->xml(), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }

            return Http::response(['documents' => [$this->metadata('doc-1')]]);
        });
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['acknowledged']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/receive/doc-1'));
        $this->assertSame($expenseId, $receipt->fresh()->business_expense_id);
        $this->assertDatabaseCount('business_expenses', 1);
        $this->assertDatabaseCount('business_expense_attachments', 1);
        $this->assertSame($this->xml(), Storage::disk('local')->get($receipt->fresh()->attachment_path));
    }

    public function test_settings_changed_during_detail_cannot_import_or_acknowledge_with_stale_credentials(): void
    {
        $company = $this->company();
        foreach ([['efaktura_inbound_enabled' => false], ['efaktura_sapi_client_secret' => 'synthetic-rotated-secret'], ['efaktura_sapi_client_id' => 'synthetic-rotated-client']] as $change) {
            $company->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($company->app_settings, [
                'efaktura_inbound_enabled' => true, 'efaktura_sapi_client_id' => 'synthetic-client', 'efaktura_sapi_client_secret' => 'synthetic-secret',
            ])]);
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(function ($request) use ($company, $change) {
                if (str_ends_with($request->url(), '/sapi/auth/token')) {
                    return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
                }
                if (str_ends_with($request->url(), '/receive/doc-1')) {
                    $fresh = $company->fresh();
                    $fresh->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($fresh->app_settings, $change)]);

                    return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => $this->xml(), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
                }

                return Http::response(['documents' => [$this->metadata('doc-1')]]);
            });
            $stats = app(EfakturaInboundService::class)->pollCompany($company->fresh());
            $this->assertSame(0, $stats['imported']);
            $this->assertSame(0, $stats['acknowledged']);
            $this->assertGreaterThan(0, $stats['failed']);
            $this->assertDatabaseCount('efaktura_inbound_receipts', 0);
            Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
        }
    }

    public function test_import_commits_separately_and_acknowledgement_has_a_fresh_settings_transaction(): void
    {
        $company = $this->company();
        $baselineLevel = DB::transactionLevel();
        $levels = [];
        Http::fake(function ($request) use (&$levels) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'expires_in' => 900, 'token_type' => 'Bearer']);
            }
            if (str_ends_with($request->url(), '/acknowledge')) {
                $levels['ack'] = DB::transactionLevel();
                $receipt = EfakturaInboundReceipt::firstOrFail();
                $this->assertSame($this->xml(), Crypt::decryptString($receipt->ubl_encrypted));

                return Http::response(['documentId' => 'doc-1', 'status' => 'ACKNOWLEDGED']);
            }
            if (str_ends_with($request->url(), '/receive/doc-1')) {
                $levels['detail'] = DB::transactionLevel();

                return Http::response(['metadata' => $this->metadata('doc-1'), 'payload' => $this->xml(), 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }

            return Http::response(['documents' => [$this->metadata('doc-1')]]);
        });
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company)['acknowledged']);
        $this->assertSame($baselineLevel, $levels['detail']);
        $this->assertSame($baselineLevel + 1, $levels['ack']);
    }

    public function test_a_change_after_import_keeps_committed_staging_without_acknowledging(): void
    {
        $company = $this->company();
        $this->fakeProvider();
        EfakturaInboundReceipt::created(function () use ($company) {
            $fresh = $company->fresh();
            $fresh->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($fresh->app_settings, ['efaktura_inbound_enabled' => false])]);
        });
        try {
            $stats = app(EfakturaInboundService::class)->pollCompany($company);
        } finally {
            EfakturaInboundReceipt::flushEventListeners();
        }
        $this->assertSame(0, $stats['acknowledged']);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $this->assertNull($receipt->acknowledged_at);
        $this->assertSame($this->xml(), Crypt::decryptString($receipt->ubl_encrypted));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
    }

    public function test_foreign_receiver_or_dtd_original_is_never_acked_or_imported(): void
    {
        $company = $this->company();
        foreach ([$this->xml('0000000000'), '<!DOCTYPE Invoice [<!ENTITY x "bad">]>'.$this->xml()] as $invalid) {
            $this->fakeProvider($invalid);
            $stats = app(EfakturaInboundService::class)->pollCompany($company->fresh());
            $this->assertSame(0, $stats['acknowledged']);
            $this->assertGreaterThan(0, $stats['failed']);
            $this->assertDatabaseCount('efaktura_inbound_receipts', 0);
            Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
        }
    }

    public function test_credit_note_is_identified_and_offsets_the_expense_amount(): void
    {
        $this->fakeProvider($this->xml(credit: true));
        app(EfakturaInboundService::class)->pollCompany($this->company());
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $this->assertSame('credit_note', $receipt->draft_json['document_type']);
        $this->assertSame('-42.50', $receipt->draft_json['total']);
    }

    public function test_production_and_test_provider_ids_do_not_collide(): void
    {
        $this->fakeProvider();
        $company = $this->company();
        app(EfakturaInboundService::class)->pollCompany($company);
        $settings = $company->fresh()->app_settings;
        $settings['efaktura_sapi_base_url'] = 'https://postman.slovakodata.com';
        $company->update(['app_settings' => $settings]);
        app(EfakturaInboundService::class)->pollCompany($company->fresh());
        $this->assertDatabaseCount('efaktura_inbound_receipts', 2);
        $this->assertSame(2, EfakturaInboundReceipt::distinct()->count('connection_key'));
    }

    public function test_digest_confirmation_is_required_and_lost_import_ack_is_idempotent(): void
    {
        $this->fakeProvider();
        $company = $this->company();
        app(EfakturaInboundService::class)->pollCompany($company);
        $receipt = EfakturaInboundReceipt::firstOrFail();
        $service = app(EfakturaInboundInboxService::class);
        foreach ([null, str_repeat('0', 64)] as $badHash) {
            try {
                $service->markImported($receipt, $company, $badHash);
                $this->fail('Expected original confirmation refusal');
            } catch (ValidationException) {
                $this->assertNotNull($receipt->fresh()->ubl_encrypted);
            }
        }
        $hash = hash('sha256', $this->xml());
        $token = $service->prepareImport($receipt, $company, $hash);
        $this->assertNotNull($receipt->fresh()->ubl_encrypted);
        $service->markImported($receipt, $company, $hash, $token);
        $service->markImported($receipt, $company, $hash, $token);
        $this->assertNull($receipt->fresh()->ubl_encrypted);
        $this->assertSame($hash, $service->detail($company->user, $company, $receipt->fresh())['original_sha256']);
    }

    public function test_changing_connection_clears_old_secret_and_epostman_sending_is_refused(): void
    {
        $settings = CompanyEfakturaSettings::mergeIncoming(['efaktura_sapi_base_url' => self::ORIGIN, 'efaktura_sapi_client_secret_encrypted' => 'old'], ['efaktura_sapi_base_url' => 'https://postman.slovakodata.com']);
        $this->assertNull($settings['efaktura_sapi_client_secret_encrypted']);
        $this->expectException(\RuntimeException::class);
        app(SapiSkClient::class)->sendDocument('synthetic-token', self::PARTICIPANT, [], baseUrl: self::ORIGIN);
    }

    public function test_default_https_port_is_the_same_connection_and_does_not_duplicate(): void
    {
        $this->fakeProvider();
        $company = $this->company();
        app(EfakturaInboundService::class)->pollCompany($company);
        $settings = $company->fresh()->app_settings;
        $settings['efaktura_sapi_base_url'] = self::ORIGIN.':443';
        $company->update(['app_settings' => $settings]);
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['skipped']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 1);
    }

    public function test_html_redirects_and_throttled_lists_do_not_look_like_an_empty_inbox(): void
    {
        $company = $this->company();
        foreach ([Http::response('<html>unavailable</html>', 200), Http::response([], 302, ['Location' => 'https://other.invalid']), Http::response(['error' => 'rate limited'], 429)] as $response) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(fn ($request) => str_ends_with($request->url(), '/sapi/auth/token')
                ? Http::response(['access_token' => 'synthetic-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null])
                : $response);
            $stats = app(EfakturaInboundService::class)->pollCompany($company->fresh());
            $this->assertGreaterThan(0, $stats['failed']);
            $this->assertSame(0, $stats['acknowledged']);
            $this->assertDatabaseCount('efaktura_inbound_receipts', 0);
            Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
        }
    }

    public function test_foreign_override_and_stale_jurisdiction_cannot_poll_the_wrong_company(): void
    {
        Http::fake();
        $company = $this->company();
        $settings = $company->app_settings;
        $settings['efaktura_peppol_participant_id'] = '0245:0000000000';
        $company->update(['app_settings' => $settings]);
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company)['failed']);
        Http::assertNothingSent();
        $stale = $this->company();
        Company::whereKey($stale->id)->update(['jurisdiction' => CompanyJurisdiction::Us->value]);
        app(EfakturaInboundService::class)->pollCompany($stale);
        Http::assertNothingSent();
    }

    public function test_repeated_cursor_is_bounded_and_legacy_receipt_is_not_silently_adopted(): void
    {
        $company = $this->company();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/sapi/auth/token')
            ? Http::response(['access_token' => 'synthetic-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null])
            : Http::response(['documents' => [], 'totalCount' => 0, 'nextPageToken' => 'same']));
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company)['failed']);
        Http::assertSentCount(3);
        EfakturaInboundReceipt::create(['company_id' => $company->id, 'external_document_id' => 'doc-1', 'status' => 'imported']);
        $this->fakeProvider();
        $this->assertSame(1, app(EfakturaInboundService::class)->pollCompany($company->fresh())['failed']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
    }

    public function test_failure_to_store_original_never_acknowledges_provider_document(): void
    {
        $this->fakeProvider();
        $inbox = \Mockery::mock(EfakturaInboundInboxService::class);
        $inbox->shouldReceive('storeAsInboxItem')->once()->andThrow(new \RuntimeException('Synthetic storage failure'));
        $this->app->instance(EfakturaInboundInboxService::class, $inbox);
        $stats = app(EfakturaInboundService::class)->pollCompany($this->company());
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['acknowledged']);
        $this->assertDatabaseCount('efaktura_inbound_receipts', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
    }

    public function test_old_poll_cannot_restore_changed_credentials_or_write_success_for_a_new_connection(): void
    {
        $company = $this->company();
        Http::fake(function ($request) use ($company) {
            if (str_ends_with($request->url(), '/sapi/auth/token')) {
                return Http::response(['access_token' => 'synthetic-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null]);
            }
            $fresh = $company->fresh();
            $fresh->update(['app_settings' => CompanyEfakturaSettings::mergeIncoming($fresh->app_settings, [
                'efaktura_sapi_base_url' => 'https://postman.slovakodata.com',
                'efaktura_sapi_client_secret' => 'synthetic-rotated-secret',
            ])]);

            return Http::response(['documents' => [], 'totalCount' => 0]);
        });
        $this->assertSame(0, app(EfakturaInboundService::class)->pollCompany($company)['failed']);
        $fresh = $company->fresh();
        $this->assertSame('https://postman.slovakodata.com', $fresh->app_settings['efaktura_sapi_base_url']);
        $this->assertSame('synthetic-rotated-secret', CompanyEfakturaSettings::fromCompany($fresh)->sapiClientSecret());
        $this->assertNull($fresh->app_settings['efaktura_inbound_last_poll_at'] ?? null);
        $this->assertSame('not_polled', app(EfakturaInboundInboxService::class)->health($fresh->user, $fresh)['state']);
    }

    public function test_llc_is_not_polled_and_company_lock_prevents_concurrent_poll(): void
    {
        Http::fake();
        $company = $this->company();
        $lock = Cache::lock('efaktura.inbound.company.'.$company->id, 300);
        $this->assertTrue($lock->get());
        try {
            $this->assertSame(0, app(EfakturaInboundService::class)->pollCompany($company)['acknowledged']);
        } finally {
            $lock->release();
        }
        $company->forceFill(['jurisdiction' => CompanyJurisdiction::Us])->save();
        app(EfakturaInboundService::class)->pollAll();
        Http::assertNothingSent();
    }
}
