<?php

namespace Tests\Unit;

use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyEfakturaPollEvidenceTest extends TestCase
{
    private const PARTICIPANT = '0245:1234567890';

    private const EVIDENCE = [
        'efaktura_connection_tested_at' => '2026-11-15T08:00:00Z',
        'efaktura_inbound_last_poll_at' => '2026-11-15T08:01:00Z',
        'efaktura_inbound_last_poll_stats' => ['imported' => 1, 'acknowledged' => 1, 'skipped' => 0, 'failed' => 0],
    ];

    private function currentSettings(): array
    {
        return array_merge([
            'efaktura_enabled' => true,
            'efaktura_inbound_enabled' => true,
            'efaktura_auto_send' => false,
            'efaktura_provider' => 'sapi_sk',
            'efaktura_peppol_participant_id' => self::PARTICIPANT,
            'efaktura_sapi_base_url' => 'https://testpostman.slovakodata.com',
            'efaktura_sapi_client_id' => 'synthetic-client',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-secret'),
            'efaktura_epostman_api_key_encrypted' => Crypt::encryptString('synthetic-enterprise-key'),
            'efaktura_epostman_status_generation' => 'synthetic-generation',
        ], self::EVIDENCE);
    }

    private function assertEvidencePreserved(array $settings): void
    {
        foreach (self::EVIDENCE as $key => $expected) {
            $this->assertSame($expected, $settings[$key] ?? null, $key);
        }
    }

    private function assertEvidenceInvalidated(array $settings): void
    {
        foreach (self::EVIDENCE as $key => $_value) {
            $this->assertArrayNotHasKey($key, $settings);
        }
    }

    #[Test]
    public function unchanged_bridge_save_preserves_poll_and_connection_test_evidence(): void
    {
        $current = $this->currentSettings();
        $merged = CompanyEfakturaSettings::mergeIncoming($current, [
            'efaktura_enabled' => true,
            'efaktura_inbound_enabled' => true,
            'efaktura_auto_send' => false,
            'efaktura_sapi_base_url' => 'https://testpostman.slovakodata.com',
            'efaktura_sapi_client_id' => 'synthetic-client',
            'efaktura_peppol_participant_id' => self::PARTICIPANT,
            'efaktura_sapi_client_secret' => 'synthetic-secret',
        ], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
        $this->assertArrayNotHasKey('efaktura_sapi_client_secret', $merged);
        $this->assertSame('synthetic-secret', Crypt::decryptString($merged['efaktura_sapi_client_secret_encrypted']));
        $this->assertSame($current['efaktura_epostman_api_key_encrypted'], $merged['efaktura_epostman_api_key_encrypted']);
        $this->assertSame($current['efaktura_epostman_status_generation'], $merged['efaktura_epostman_status_generation']);
    }

    #[Test]
    public function normalized_equal_secret_and_unrelated_preferences_preserve_evidence(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming($this->currentSettings(), [
            'efaktura_sapi_client_secret' => '  synthetic-secret  ',
            'show_contextual_help' => false,
        ], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
        $this->assertFalse($merged['show_contextual_help']);
        $this->assertSame('synthetic-secret', Crypt::decryptString($merged['efaktura_sapi_client_secret_encrypted']));
    }

    #[Test]
    public function omitting_credentials_preserves_existing_evidence_and_ciphertext(): void
    {
        $current = $this->currentSettings();
        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['show_contextual_help' => false], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
        $this->assertSame($current['efaktura_sapi_client_secret_encrypted'], $merged['efaktura_sapi_client_secret_encrypted']);
    }

    #[Test]
    public function equal_legacy_plaintext_is_migrated_to_encryption_without_losing_evidence(): void
    {
        $current = $this->currentSettings();
        unset($current['efaktura_sapi_client_secret_encrypted']);
        $current['efaktura_sapi_client_secret'] = 'synthetic-secret';

        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_sapi_client_secret' => 'synthetic-secret'], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
        $this->assertArrayNotHasKey('efaktura_sapi_client_secret', $merged);
        $this->assertSame('synthetic-secret', Crypt::decryptString($merged['efaktura_sapi_client_secret_encrypted']));
    }

    #[Test]
    public function rotated_secret_invalidates_evidence_without_revoking_an_unrelated_enterprise_key(): void
    {
        $current = $this->currentSettings();
        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_sapi_client_secret' => 'synthetic-rotated-secret'], self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
        $this->assertSame('synthetic-rotated-secret', Crypt::decryptString($merged['efaktura_sapi_client_secret_encrypted']));
        $this->assertSame($current['efaktura_epostman_api_key_encrypted'], $merged['efaktura_epostman_api_key_encrypted']);
    }

    public static function clearedSecrets(): array
    {
        return ['null' => [null], 'empty' => [''], 'whitespace' => ['   ']];
    }

    #[Test]
    #[DataProvider('clearedSecrets')]
    public function clearing_a_secret_invalidates_evidence_and_cannot_restore_legacy_plaintext(?string $value): void
    {
        $current = $this->currentSettings();
        $current['efaktura_sapi_client_secret'] = 'synthetic-legacy-secret';
        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_sapi_client_secret' => $value], self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
        $this->assertNull($merged['efaktura_sapi_client_secret_encrypted']);
        $this->assertArrayNotHasKey('efaktura_sapi_client_secret', $merged);
        $this->assertFalse((new CompanyEfakturaSettings($merged, self::PARTICIPANT))->configured());
    }

    public static function unreadableSecrets(): array
    {
        return ['missing' => [null], 'corrupt' => ['synthetic-unreadable-ciphertext'], 'empty' => ['']];
    }

    #[Test]
    #[DataProvider('unreadableSecrets')]
    public function a_new_secret_cannot_inherit_evidence_from_an_unreadable_previous_credential(?string $ciphertext): void
    {
        $current = $this->currentSettings();
        $current['efaktura_sapi_client_secret_encrypted'] = $ciphertext;
        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_sapi_client_secret' => 'synthetic-secret'], self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
        $this->assertSame('synthetic-secret', Crypt::decryptString($merged['efaktura_sapi_client_secret_encrypted']));
    }

    public static function changedConnections(): array
    {
        return [
            'provider' => ['efaktura_provider', 'synthetic-other-provider'],
            'origin' => ['efaktura_sapi_base_url', 'https://postman.slovakodata.com'],
            'client' => ['efaktura_sapi_client_id', 'synthetic-other-client'],
            'participant' => ['efaktura_peppol_participant_id', '0245:9999999999'],
            'module off' => ['efaktura_enabled', false],
        ];
    }

    #[Test]
    #[DataProvider('changedConnections')]
    public function changing_a_connection_field_invalidates_evidence_even_with_the_same_secret(string $field, mixed $value): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming($this->currentSettings(), [
            $field => $value,
            'efaktura_sapi_client_secret' => 'synthetic-secret',
        ], self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
    }

    #[Test]
    public function disabling_receiving_invalidates_poll_evidence_but_preserves_sender_authentication(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming($this->currentSettings(), [
            'efaktura_inbound_enabled' => false,
            'efaktura_sapi_client_secret' => 'synthetic-secret',
        ], self::PARTICIPANT);

        $this->assertSame(self::EVIDENCE['efaktura_connection_tested_at'], $merged['efaktura_connection_tested_at']);
        $this->assertArrayNotHasKey('efaktura_inbound_last_poll_at', $merged);
        $this->assertArrayNotHasKey('efaktura_inbound_last_poll_stats', $merged);
    }

    #[Test]
    public function clearing_an_unreadable_shared_secret_cannot_preserve_old_poll_evidence(): void
    {
        $current = $this->currentSettings();
        $current['efaktura_sapi_client_secret_encrypted'] = 'synthetic-unreadable-ciphertext';
        $merged = CompanyEfakturaSettings::mergeIncoming($current, ['efaktura_sapi_client_secret' => null], self::PARTICIPANT);
        $this->assertEvidenceInvalidated($merged);
    }

    #[Test]
    public function incoming_metadata_cannot_replace_trusted_evidence_during_an_unchanged_save(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming($this->currentSettings(), [
            'efaktura_sapi_client_secret' => 'synthetic-secret',
            'efaktura_connection_tested_at' => '2099-01-01T00:00:00Z',
            'efaktura_inbound_last_poll_at' => '2099-01-01T00:00:00Z',
            'efaktura_inbound_last_poll_stats' => ['failed' => 0, 'imported' => 99],
        ], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
    }

    #[Test]
    public function incoming_metadata_cannot_create_evidence_when_none_was_stored(): void
    {
        $current = array_diff_key($this->currentSettings(), self::EVIDENCE);
        $merged = CompanyEfakturaSettings::mergeIncoming($current, self::EVIDENCE, self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
    }

    #[Test]
    public function incoming_ciphertext_cannot_bypass_the_write_only_secret_input(): void
    {
        $current = $this->currentSettings();
        $merged = CompanyEfakturaSettings::mergeIncoming($current, [
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-injected-secret'),
        ], self::PARTICIPANT);

        $this->assertEvidencePreserved($merged);
        $this->assertSame($current['efaktura_sapi_client_secret_encrypted'], $merged['efaktura_sapi_client_secret_encrypted']);
        $this->assertSame('synthetic-secret', (new CompanyEfakturaSettings($merged))->sapiClientSecret());
    }

    #[Test]
    public function changing_identity_still_revokes_the_enterprise_key(): void
    {
        $merged = CompanyEfakturaSettings::mergeIncoming($this->currentSettings(), [
            'efaktura_peppol_participant_id' => '0245:9999999999',
            'efaktura_sapi_client_secret' => 'synthetic-secret',
        ], self::PARTICIPANT);

        $this->assertEvidenceInvalidated($merged);
        $this->assertNull($merged['efaktura_epostman_api_key_encrypted']);
        $this->assertNotSame('synthetic-generation', $merged['efaktura_epostman_status_generation']);
    }
}
