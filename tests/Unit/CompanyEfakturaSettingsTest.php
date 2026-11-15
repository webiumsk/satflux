<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyEfakturaSettingsTest extends TestCase
{
    private function skCompany(array $attributes = []): Company
    {
        return new Company(array_merge([
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'registration_number' => '47615681',
            'tax_id' => '2023980035',
            'vat_payer' => true,
            'vat_status' => 'payer',
        ], $attributes));
    }

    #[Test]
    public function participant_id_derives_from_dic_when_no_explicit_value(): void
    {
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany());

        $this->assertNull($settings->explicitPeppolParticipantId());
        $this->assertSame('0245:2023980035', $settings->derivedPeppolParticipantId());
        $this->assertSame('0245:2023980035', $settings->peppolParticipantId());
    }

    #[Test]
    public function participant_id_falls_back_to_ico_without_dic(): void
    {
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany(['tax_id' => null]));

        $this->assertSame('0158:47615681', $settings->peppolParticipantId());
    }

    #[Test]
    public function explicit_participant_id_wins_over_the_derived_one(): void
    {
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany([
            'app_settings' => ['efaktura_peppol_participant_id' => '0245:9999999999'],
        ]));

        $this->assertSame('0245:9999999999', $settings->explicitPeppolParticipantId());
        $this->assertSame('0245:2023980035', $settings->derivedPeppolParticipantId());
        $this->assertSame('0245:9999999999', $settings->peppolParticipantId());
    }

    #[Test]
    public function configured_accepts_the_derived_participant_id(): void
    {
        // No explicit efaktura_peppol_participant_id - the DIČ derivation
        // must be enough, so merchants never type the scheme syntax.
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany([
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_sapi_base_url' => 'https://sapi.test',
                'efaktura_sapi_client_id' => 'client-test',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('secret-test'),
            ],
        ]));

        $this->assertTrue($settings->configured());
    }

    #[Test]
    public function public_payload_separates_explicit_and_derived_ids(): void
    {
        $payload = CompanyEfakturaSettings::fromCompany($this->skCompany())->publicPayload();

        $this->assertNull($payload['efaktura_peppol_participant_id']);
        $this->assertSame('0245:2023980035', $payload['efaktura_peppol_participant_id_derived']);
        $this->assertArrayHasKey('efaktura_connection_tested_at', $payload);
    }

    #[Test]
    public function separate_receiving_never_falls_back_to_shared_or_global_credentials(): void
    {
        config(['efaktura.providers.sapi_sk.base_url' => 'https://global.invalid']);
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany(['app_settings' => [
            'efaktura_enabled' => true,
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_sapi_base_url' => 'https://sender.invalid',
            'efaktura_sapi_client_id' => 'synthetic-sender',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sender-secret'),
        ]]));

        $this->assertFalse($settings->forInbound()->configured());
        $this->assertNull($settings->forInbound()->sapiBaseUrl());
        $this->assertNull($settings->forInbound()->sapiClientId());
        $this->assertNull($settings->forInbound()->sapiClientSecret());
        $this->assertTrue($settings->configured());
    }

    #[Test]
    public function receiving_changes_preserve_new_and_legacy_sender_bindings(): void
    {
        $settings = CompanyEfakturaSettings::fromCompany($this->skCompany(['app_settings' => [
            'efaktura_enabled' => true, 'efaktura_inbound_enabled' => true,
            'efaktura_sapi_base_url' => 'https://sender.invalid',
            'efaktura_sapi_client_id' => 'synthetic-sender',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sender-secret'),
        ]]));
        $legacy = $settings->connectionFingerprint();
        $outgoing = $settings->outboundConnectionFingerprint();
        $changed = new CompanyEfakturaSettings(CompanyEfakturaSettings::mergeIncoming($settings->values, [
            'efaktura_inbound_enabled' => false,
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_inbound_sapi_base_url' => 'https://receiver.invalid',
            'efaktura_inbound_sapi_client_id' => 'synthetic-receiver',
            'efaktura_inbound_sapi_client_secret' => 'synthetic-receiver-secret',
        ]), $settings->derivedPeppolParticipantId());

        $this->assertSame($outgoing, $changed->outboundConnectionFingerprint());
        $this->assertTrue($changed->matchesOutboundConnectionFingerprint($legacy));
        $this->assertTrue($changed->matchesOutboundConnectionFingerprint($outgoing));
        $changed->values['efaktura_sapi_client_secret_encrypted'] = Crypt::encryptString('synthetic-rotated-sender-secret');
        $this->assertFalse($changed->matchesOutboundConnectionFingerprint($legacy));
        $this->assertFalse($changed->matchesOutboundConnectionFingerprint($outgoing));
    }

    #[Test]
    public function unchanged_receiver_preserves_its_proof_when_sender_changes(): void
    {
        $values = [
            'efaktura_enabled' => true, 'efaktura_inbound_enabled' => true,
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_sapi_base_url' => 'https://sender.invalid',
            'efaktura_sapi_client_id' => 'synthetic-sender',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sender-secret'),
            'efaktura_connection_tested_at' => '2026-11-15T10:00:00Z',
            'efaktura_inbound_sapi_base_url' => 'https://receiver.invalid',
            'efaktura_inbound_sapi_client_id' => 'synthetic-receiver',
            'efaktura_inbound_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-receiver-secret'),
            'efaktura_inbound_connection_tested_at' => '2026-11-15T10:01:00Z',
            'efaktura_inbound_last_poll_at' => '2026-11-15T10:02:00Z',
        ];
        $merged = CompanyEfakturaSettings::mergeIncoming($values, [
            'efaktura_sapi_client_secret' => 'synthetic-replaced-sender-secret',
            'efaktura_inbound_sapi_client_secret' => 'synthetic-receiver-secret',
        ]);

        $this->assertNull($merged['efaktura_connection_tested_at'] ?? null);
        $this->assertSame($values['efaktura_inbound_connection_tested_at'], $merged['efaktura_inbound_connection_tested_at']);
        $this->assertSame($values['efaktura_inbound_last_poll_at'], $merged['efaktura_inbound_last_poll_at']);

        $changed = CompanyEfakturaSettings::mergeIncoming($values, ['efaktura_inbound_sapi_client_id' => 'synthetic-replaced-receiver']);
        $this->assertSame($values['efaktura_connection_tested_at'], $changed['efaktura_connection_tested_at']);
        $this->assertNull($changed['efaktura_inbound_sapi_client_secret_encrypted']);
        $this->assertNull($changed['efaktura_inbound_connection_tested_at'] ?? null);
        $this->assertNull($changed['efaktura_inbound_last_poll_at'] ?? null);
    }

    #[Test]
    public function receiver_ciphertext_and_test_evidence_cannot_be_injected(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming([], [
            'efaktura_inbound_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-injected'),
            'efaktura_inbound_sapi_client_secret_set' => true,
            'efaktura_inbound_connection_tested_at' => '2026-11-15T10:00:00Z',
        ]);
        $this->assertNull((new CompanyEfakturaSettings($merged))->separateInboundSecret());
        $this->assertArrayNotHasKey('efaktura_inbound_sapi_client_secret_set', $merged);
        $this->assertArrayNotHasKey('efaktura_inbound_connection_tested_at', $merged);
        $this->assertTrue((new CompanyEfakturaSettings)->inboundUsesSharedConnection());
    }

    #[Test]
    public function receiver_credential_rotation_retains_outgoing_enterprise_key_and_status_generation(): void
    {
        $values = [
            'efaktura_enabled' => true, 'efaktura_inbound_enabled' => true,
            'efaktura_sapi_base_url' => 'https://testpostman.slovakodata.com',
            'efaktura_epostman_api_key_encrypted' => Crypt::encryptString('synthetic-enterprise-key'),
            'efaktura_epostman_status_generation' => 'synthetic-generation',
        ];
        $before = new CompanyEfakturaSettings($values, '0245:1234567890');
        $merged = CompanyEfakturaSettings::mergeIncoming($values, [
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_inbound_sapi_base_url' => 'https://receiver.invalid',
            'efaktura_inbound_sapi_client_id' => 'synthetic-receiver-client',
            'efaktura_inbound_sapi_client_secret' => 'synthetic-receiver-secret',
        ], '0245:1234567890');
        $this->assertSame($values['efaktura_epostman_api_key_encrypted'], $merged['efaktura_epostman_api_key_encrypted']);
        $this->assertSame($values['efaktura_epostman_status_generation'], $merged['efaktura_epostman_status_generation']);
        $this->assertSame($before->enterpriseStatusFingerprint(), (new CompanyEfakturaSettings($merged, '0245:1234567890'))->enterpriseStatusFingerprint());
    }

    #[Test]
    public function clearing_an_unreadable_receiver_secret_invalidates_all_receiving_proof(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming([
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_inbound_sapi_client_secret_encrypted' => 'synthetic-unreadable-ciphertext',
            'efaktura_inbound_connection_tested_at' => '2026-11-15T10:00:00Z',
            'efaktura_inbound_last_poll_at' => '2026-11-15T10:00:00Z',
            'efaktura_inbound_last_poll_stats' => ['failed' => 0],
        ], ['efaktura_inbound_sapi_client_secret' => null]);
        $this->assertNull($merged['efaktura_inbound_sapi_client_secret_encrypted']);
        $this->assertArrayNotHasKey('efaktura_inbound_connection_tested_at', $merged);
        $this->assertArrayNotHasKey('efaktura_inbound_last_poll_at', $merged);
        $this->assertArrayNotHasKey('efaktura_inbound_last_poll_stats', $merged);
    }
}
