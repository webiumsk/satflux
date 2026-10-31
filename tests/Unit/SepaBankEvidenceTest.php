<?php

namespace Tests\Unit;

use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Support\Invoicing\BankAccountIdentifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class SepaBankEvidenceTest extends TestCase
{
    #[Test]
    #[TestWith(['sk48 1100 0000 0029 4411 6480', 'SK4811000000002944116480'])]
    #[TestWith(['CZ65 0800 0000 1920 0014 5399', 'CZ6508000000192000145399'])]
    public function full_checksum_valid_receiving_accounts_are_normalized(string $input, string $expected): void
    {
        $this->assertSame($expected, BankAccountIdentifier::normalize($input));
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['SK9812353347235'])]
    #[TestWith(['SK4811000000002944116481'])]
    #[TestWith(['CZ6508000000192000145398'])]
    #[TestWith(['SK4811000000002944116480 extra'])]
    public function missing_or_invalid_accounts_are_not_beneficiary_evidence(?string $input): void
    {
        $this->assertNull(BankAccountIdentifier::normalize($input));
    }

    #[Test]
    #[TestWith(['notify@tatrabanka.sk', 'tatrabanka.sk'])]
    #[TestWith(['Tatra Bank <notify@alerts.tatrabanka.sk>', 'tatrabanka.sk'])]
    #[TestWith(['notify@slsp.sk', 'mail.slsp.sk'])]
    public function aligned_supported_bank_attestation_is_accepted(string $from, string $domain): void
    {
        $this->assertSame($domain, (new BankEmailOriginVerifier)->nativeVerifiedDomain([
            'dkim_result' => 'pass', 'dkim_domain' => $domain, 'dmarc_result' => 'pass',
        ], $from));
    }

    #[Test]
    #[TestWith(['notify@tatrabanka.sk', 'slsp.sk'])]
    #[TestWith(['notify@attacker.test', 'tatrabanka.sk'])]
    #[TestWith(['notify@tatrabanka.sk.attacker.test', 'tatrabanka.sk'])]
    #[TestWith(['notify@tatrabanka.sk', 'tatrabanka.sk.attacker.test'])]
    #[TestWith(['notify@tatrabanka.sk, attacker@attacker.test', 'tatrabanka.sk'])]
    public function mailbox_or_signing_domain_impersonation_is_rejected(string $from, string $domain): void
    {
        $this->assertNull((new BankEmailOriginVerifier)->nativeVerifiedDomain([
            'dkim_result' => 'pass', 'dkim_domain' => $domain, 'dmarc_result' => 'pass',
        ], $from));
    }

    #[Test]
    public function generic_delivery_flags_and_malformed_attestations_are_not_bank_authentication(): void
    {
        $verifier = new BankEmailOriginVerifier;
        foreach ([null, true, 'pass', ['dkim_result' => 'pass'], [
            'dkim_result' => 'pass', 'dkim_domain' => ['tatrabanka.sk'], 'dmarc_result' => 'pass',
        ]] as $attestation) {
            $this->assertNull($verifier->nativeVerifiedDomain($attestation, 'notify@tatrabanka.sk'));
        }
    }
}
