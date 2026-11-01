<?php

namespace Tests\Unit;

use App\Services\Invoicing\BankImport\SlspBankEmailParser;
use App\Services\Invoicing\BankImport\SlspBankSepaEvidenceParser;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SlspBankSepaEvidenceParserTest extends TestCase
{
    public static function unqualifiedBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'arbitrary-labels' => ['Suma: 150,50 EUR. VS: 20260042. QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];
        yield 'synthetic-tatra-frame' => [TatraBankSepaEvidenceDiagnosticsTest::body()];
        yield 'synthetic-tatra-platba-frame' => [TatraBankSepaEvidenceDiagnosticsTest::platbaBody()];
        yield 'html' => ['<div>'.TatraBankSepaEvidenceDiagnosticsTest::body().'</div>'];
        yield 'invalid-encoding' => ["synthetic\xE9\0"];
        yield 'oversized' => [str_repeat('x', 65536)];
    }

    #[DataProvider('unqualifiedBodies')]
    public function test_disabled_profile_never_returns_evidence(string $body): void
    {
        $this->assertNull((new SlspBankSepaEvidenceParser)->parse($body));
    }

    #[TestWith(['notify@slsp.sk'])]
    #[TestWith(['notify@alerts.slsp.sk'])]
    #[TestWith(['Tatra banka <notify@slsp.sk>'])]
    #[TestWith(['tatrabanka.sk@slsp.sk'])]
    #[TestWith(['notify@tatrabanka.sk.attacker.test'])]
    #[TestWith(['not-a-mailbox'])]
    public function test_generic_and_inherited_parsers_cannot_promote_other_senders_to_tatra_evidence(string $from): void
    {
        // These are existing synthetic Tatra frames, never purported SLSP samples.
        foreach ([TatraBankSepaEvidenceDiagnosticsTest::body(), TatraBankSepaEvidenceDiagnosticsTest::body('/VS1234567890/SS/KS')] as $body) {
            foreach ([new TatraBankEmailParser, new SlspBankEmailParser] as $parser) {
                $rows = $parser->parse($from, 'Tatra banka obrat', $body);
                $this->assertCount(1, $rows);
                $this->assertNull($rows[0]->e2eReference);
                $this->assertFalse($rows[0]->isVariableSymbolExplicit);
                $this->assertNull($rows[0]->sepaCreditIdentity);
            }
        }
    }

    #[TestWith(['CCINT', '9860306e4ad7d2e1d68a7dd255bc569ecd1b885e502016a62ee3299e175f4137'])]
    #[TestWith(['CSO', 'b50db53a436a2b0d5c99cbcfad4a55fc7c603bb455f42d6fa017edb30ddf3bd6'])]
    #[TestWith(['Platba', '592726f4848318a1d34384d1f834da3f320de0086a4a68f5a5ddc9e068f1e3f8'])]
    public function test_tatra_sender_keeps_exact_golden_evidence_even_with_slsp_display_or_subject(string $label, string $identity): void
    {
        $body = $label === 'Platba' ? TatraBankSepaEvidenceDiagnosticsTest::platbaBody()
            : str_replace('CCINT', $label, TatraBankSepaEvidenceDiagnosticsTest::body());
        foreach ([new TatraBankEmailParser, new SlspBankEmailParser] as $parser) {
            $row = $parser->parse('SLSP <notify@alerts.tatrabanka.sk>', 'SLSP notification', $body)[0];
            $this->assertSame($identity, $row->sepaCreditIdentity);
            $this->assertSame($label === 'Platba' ? 'QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' : 'QR-ab29e346f1d841c8a95a63d857490818', $row->e2eReference);
            $this->assertTrue($row->isExplicitCredit);
            $this->assertTrue($row->hasExplicitCurrency);
            $this->assertSame('SK4811000000002944116480', $row->recipientIban);
        }
    }

    public function test_existing_generic_company_row_metadata_is_preserved(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 10:11:12', 'UTC'));
        try {
            $body = 'Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026';
            foreach ([new TatraBankEmailParser, new SlspBankEmailParser] as $parser) {
                $row = $parser->parse('notify@slsp.sk', 'Synthetic import', $body)[0];
                $fields = $row->toRow();
                $this->assertSame('2026-06-01 10:11:12', $fields['booked_at']->format('Y-m-d H:i:s'));
                unset($fields['booked_at']);
                $this->assertSame([
                    'amount' => 150.5, 'currency' => 'EUR', 'direction' => 'credit',
                    'variable_symbol' => '20260042', 'constant_symbol' => null, 'specific_symbol' => null,
                    'counterparty_name' => 'Client s.r.o.', 'counterparty_iban' => null,
                    'reference' => 'Synthetic import', 'bank_transaction_id' => null,
                ], $fields);
                $this->assertFalse($row->isExplicitCredit);
                $this->assertTrue($row->hasExplicitCurrency);
                $this->assertNull($row->e2eReference);
                $this->assertFalse($row->isVariableSymbolExplicit);
                $this->assertNull($row->sepaCreditIdentity);
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
