<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TatraBankEmailParserTest extends TestCase
{
    private const RECIPIENT_IBAN = 'SK4811000000002944116480';

    private const E2E_REFERENCE = 'QR-ab29e346f1d841c8a95a63d857490818';

    /** Public historical structure with synthetic valid account, amounts and QR E2E. */
    private function strictBody(string $reference = self::E2E_REFERENCE, string $note = 'Invoice payment'): string
    {
        return "Vazeny klient,\n"
            .'01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            ."uctovny zostatok: 1 012,50 EUR\naktualny zostatok: 1 012,50 EUR\n"
            ."disponibilny zostatok: 1 012,50 EUR\nPopis transakcie: CCINT 1100/000000-261426464\n"
            .'Referencia platitela: '.$reference."\nInformacia pre prijemcu: ".$note."\n"
            ."S pozdravom\nTATRA BANKA, a.s.";
    }

    #[Test]
    #[TestWith(['QR-ab29e346f1d841c8a95a63d857490818', false])]
    #[TestWith(['QR-AB29E346F1D841C8A95A63D857490818', false])]
    #[TestWith(['/VS1234567890/SS/KS', false])]
    #[TestWith(['QR-ab29e346f1d841c8a95a63d857490818', true])]
    public function strict_historical_structure_exposes_only_the_bounded_bank_reference(string $reference, bool $counterpartyTail): void
    {
        $body = $this->strictBody($reference);
        if ($counterpartyTail) {
            $body = str_replace('Informacia pre prijemcu: Invoice payment', 'Ucet protistrany: Synthetic Payer', $body);
        }
        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertTrue($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
        $this->assertSame(self::RECIPIENT_IBAN, $row->recipientIban);
        $this->assertSame(12.50, $row->amount);
        $this->assertSame(str_starts_with($reference, 'QR-') ? $reference : null, $row->e2eReference);
        $this->assertSame(! str_starts_with($reference, 'QR-'), $row->isVariableSymbolExplicit);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->sepaCreditIdentity);
        $this->assertNull($row->bankTransactionId);
        $this->assertArrayNotHasKey('sepa_credit_identity', $row->toRow());
    }

    #[Test]
    public function documented_cso_description_and_literal_ampersand_note_keep_the_bounded_reference(): void
    {
        $body = str_replace('CCINT', 'CSO', $this->strictBody(note: 'Invoice R&D'));
        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];
        $this->assertSame(self::E2E_REFERENCE, $row->e2eReference);
        $this->assertNotNull($row->sepaCreditIdentity);
        $this->assertSame('Invoice R&D', $row->reference);
    }

    #[Test]
    #[TestWith(['missing-balance'])]
    #[TestWith(['reordered-balances'])]
    #[TestWith(['duplicate-reference'])]
    #[TestWith(['reference-after-remittance'])]
    #[TestWith(['missing-reference'])]
    #[TestWith(['free-field-before-reference'])]
    #[TestWith(['nested-reference'])]
    #[TestWith(['nested-header'])]
    #[TestWith(['missing-footer'])]
    #[TestWith(['invalid-date'])]
    #[TestWith(['invalid-hour'])]
    #[TestWith(['invalid-account'])]
    #[TestWith(['debit'])]
    #[TestWith(['zero-credit'])]
    #[TestWith(['wrong-currency'])]
    #[TestWith(['description-name'])]
    #[TestWith(['html'])]
    #[TestWith(['entity'])]
    #[TestWith(['control'])]
    #[TestWith(['bidi'])]
    #[TestWith(['nested-vs-after-blank-reference'])]
    public function unsupported_or_injected_structures_never_produce_sepa_reference_evidence(string $case): void
    {
        $body = $this->strictBody();
        $referenceLine = 'Referencia platitela: '.self::E2E_REFERENCE;
        $body = match ($case) {
            'missing-balance' => str_replace("aktualny zostatok: 1 012,50 EUR\n", '', $body),
            'reordered-balances' => str_replace('uctovny zostatok', 'disponibilny zostatok', $body),
            'duplicate-reference' => str_replace($referenceLine, $referenceLine."\n".$referenceLine, $body),
            'reference-after-remittance' => str_replace($referenceLine."\nInformacia pre prijemcu: Invoice payment", "Informacia pre prijemcu: Invoice payment\n".$referenceLine, $body),
            'missing-reference' => str_replace($referenceLine, 'Referencia platitela: ', $body),
            'free-field-before-reference' => str_replace($referenceLine, "Protistrana: Synthetic Payer\n".$referenceLine, $body),
            'nested-reference' => str_replace('Invoice payment', 'Invoice payment '.$referenceLine, $body),
            'nested-header' => str_replace('Invoice payment', 'Invoice payment bol zostatok Vasho uctu', $body),
            'missing-footer' => str_replace("\nS pozdravom\nTATRA BANKA, a.s.", '', $body),
            'invalid-date' => str_replace('01.06.2026', '31.02.2026', $body),
            'invalid-hour' => str_replace('12:31', '25:31', $body),
            'invalid-account' => str_replace(self::RECIPIENT_IBAN, 'SK4811000000002944116481', $body),
            'debit' => str_replace('zvyseny', 'znizeny', $body),
            'zero-credit' => str_replace('o 12,50 EUR.', 'o 0,00 EUR.', $body),
            'wrong-currency' => str_replace('EUR', 'USD', $body),
            'description-name' => str_replace('CCINT 1100/000000-261426464', 'Synthetic Payer', $body),
            'html' => '<div>'.$body.'</div>',
            'entity' => str_replace('Invoice payment', 'Invoice payment &#10;'.$referenceLine, $body),
            'control' => str_replace('Invoice payment', "Invoice payment\0", $body),
            'bidi' => str_replace('Invoice payment', "Invoice payment\u{202e}", $body),
            'nested-vs-after-blank-reference' => str_replace([$referenceLine, 'Invoice payment'], ['Referencia platitela: ', "Invoice payment\nReferencia platitela: /VS1234567890/SS/KS"], $body),
        };
        $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body);

        foreach ($rows as $row) {
            $this->assertNull($row->e2eReference);
            $this->assertFalse($row->isVariableSymbolExplicit);
            $this->assertNull($row->sepaCreditIdentity);
        }
    }

    #[Test]
    public function credit_fingerprint_is_independent_of_candidate_remittance_and_rendering(): void
    {
        $parser = new TatraBankEmailParser;
        $base = $this->strictBody();
        $referenceChanged = $this->strictBody('QR-00000000000000000000000000000002', 'Another invoice');
        $vsChanged = $this->strictBody('/VS1234567890/SS/KS', 'Other note');
        $renderingChanged = str_replace(['01.06.2026', '1 012,50', '1100/000000-261426464', "\n"], ['1.6.2026', '1012,50', '1100/0-0261426464', "\r\n  "], $base);
        $tailChanged = str_replace('Informacia pre prijemcu: Invoice payment', 'Ucet protistrany: Synthetic Payer', $base);
        $identity = $parser->parse('notify@tatrabanka.sk', 'Obrat', $base)[0]->sepaCreditIdentity;
        $this->assertNotNull($identity);
        foreach ([$referenceChanged, $vsChanged, $renderingChanged, $tailChanged] as $body) {
            $row = $parser->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];
            $this->assertSame($identity, $row->sepaCreditIdentity);
        }
        $row = $parser->parse('notify@tatrabanka.sk', 'Obrat', str_replace('1 012,50', '1 025,00', $base))[0];
        $this->assertNotSame($identity, $row->sepaCreditIdentity);
    }

    #[Test]
    public function a_strict_tatra_body_does_not_select_that_profile_for_another_bank(): void
    {
        $row = (new TatraBankEmailParser)->parse('notify@slsp.sk', 'Obrat', $this->strictBody())[0];
        $this->assertNull($row->e2eReference);
        $this->assertFalse($row->isVariableSymbolExplicit);
        $this->assertNull($row->sepaCreditIdentity);
        $this->assertSame('Invoice payment', $row->toRow()['reference']);
    }

    #[Test]
    public function an_inferred_invoice_number_remains_import_metadata_instead_of_an_explicit_payment_symbol(): void
    {
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Informacia pre prijemcu: Invoice 1234567890';

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertSame('1234567890', $row->variableSymbol);
        $this->assertFalse($row->isVariableSymbolExplicit);
        $this->assertNull($row->e2eReference);
    }

    #[Test]
    public function payer_remittance_cannot_promote_a_compact_amount_to_settlement_evidence(): void
    {
        $body = "Prijata platba 1,00 EUR\nVas ucet: ".self::RECIPIENT_IBAN."\n"
            ."Informacia pre prijemcu: Suma: 100,00 EUR\nReferencia platitela: ".self::E2E_REFERENCE;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Prijata platba', $body)[0];

        $this->assertFalse($row->isExplicitCredit);
    }

    #[Test]
    public function an_embedded_classic_notification_in_payer_text_is_not_a_bank_credit_header(): void
    {
        $body = "Prijata platba 1,00 EUR\nInformacia pre prijemcu:\n"
            .'01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 100,00 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Prijata platba', $body)[0];

        $this->assertFalse($row->isExplicitCredit);
    }

    #[Test]
    public function classic_bank_header_keeps_actual_amount_currency_and_account_over_remittance_text(): void
    {
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 1,00 EUR.\n"
            ."Informacia pre prijemcu: Suma: 100,00 USD. Vas ucet: SK6807200002891987426353\n"
            .'Referencia platitela: '.self::E2E_REFERENCE;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertTrue($row->isExplicitCredit);
        $this->assertSame(1.0, $row->amount);
        $this->assertSame('EUR', $row->currency);
        $this->assertSame(self::RECIPIENT_IBAN, $row->recipientIban);
    }

    #[Test]
    public function classic_credit_carries_observed_amount_and_recipient_but_no_trusted_reference(): void
    {
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE."\n"
            .'Informacia pre prijemcu: Invoice payment';

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertSame(self::RECIPIENT_IBAN, $row->recipientIban);
        $this->assertNull($row->counterpartyIban);
        $this->assertTrue($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
        $this->assertNull($row->e2eReference);
        $this->assertFalse($row->isVariableSymbolExplicit);
    }

    #[Test]
    public function invalid_own_account_is_not_promoted_to_beneficiary_evidence(): void
    {
        $body = "01.06.2026 12:31 bol zostatok Vasho uctu SK4811000000002944116481 zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertNull($row->recipientIban);
        $this->assertTrue($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
    }

    #[Test]
    public function compact_import_defaults_are_not_explicit_settlement_proof(): void
    {
        $row = (new TatraBankEmailParser)->parse(
            'notify@tatrabanka.sk',
            'Obrat na ucte',
            'Suma: 12,50. VS: 1234567890. 01.06.2026',
        )[0];

        $this->assertSame(BankTransactionDirection::Credit, $row->direction);
        $this->assertSame('EUR', $row->currency);
        $this->assertNull($row->recipientIban);
        $this->assertFalse($row->isExplicitCredit);
        $this->assertFalse($row->hasExplicitCurrency);
    }

    #[Test]
    public function an_explicit_compact_date_keeps_date_precision_across_processing_clocks(): void
    {
        $parser = new TatraBankEmailParser;
        $body = 'Suma: 12,50 EUR. VS: 1234567890. 01.06.2026';
        $this->travelTo(Carbon::parse('2026-11-01 12:00:01'));
        $first = $parser->parse('notify@tatrabanka.sk', 'Obrat na ucte', $body)[0];
        $this->travelTo(Carbon::parse('2026-11-02 23:59:59'));
        $retry = $parser->parse('notify@tatrabanka.sk', 'Obrat na ucte', $body)[0];

        $this->assertSame('2026-06-01 00:00:00', $first->bookedAt->format('Y-m-d H:i:s'));
        $this->assertEquals($first->toRow(), $retry->toRow());
        $this->assertFalse($retry->isExplicitCredit);
        $this->assertFalse($retry->isVariableSymbolExplicit);
        $this->assertNull($retry->e2eReference);
        $this->assertNull($retry->sepaCreditIdentity);
    }

    #[Test]
    public function compact_messages_without_a_date_keep_the_existing_processing_time_fallback(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:34:56'));
        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat na ucte', 'Suma: 12,50 EUR. VS: 1234567890.')[0];

        $this->assertSame('2026-11-01 12:34:56', $row->bookedAt->format('Y-m-d H:i:s'));
        $this->assertFalse($row->isExplicitCredit);
        $this->assertNull($row->sepaCreditIdentity);
    }

    #[Test]
    public function explicit_currency_does_not_make_unknown_compact_direction_a_credit_proof(): void
    {
        $row = (new TatraBankEmailParser)->parse(
            'notify@tatrabanka.sk',
            'Obrat na ucte',
            'Suma: 12,50 EUR. VS: 1234567890. 01.06.2026',
        )[0];

        $this->assertFalse($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
        $this->assertNull($row->recipientIban);
    }

    #[Test]
    public function a_body_label_does_not_prove_a_bank_owned_reference_field(): void
    {
        $otherReference = 'QR-00000000000000000000000000000001';
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE."\n"
            .'Informacia pre prijemcu: See unrelated reference '.$otherReference;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertNull($row->e2eReference);
        $this->assertFalse($row->isVariableSymbolExplicit);
    }

    #[Test]
    public function classic_debit_preserves_import_direction_without_credit_proof(): void
    {
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN.' znizeny o 12,50 EUR.';

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body)[0];

        $this->assertSame(BankTransactionDirection::Debit, $row->direction);
        $this->assertSame(self::RECIPIENT_IBAN, $row->recipientIban);
        $this->assertFalse($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
    }

    #[Test]
    public function parses_credit_with_variable_symbol(): void
    {
        $parser = new TatraBankEmailParser;
        $body = 'Obrat na ucte. Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026';

        $this->assertTrue($parser->supports('notify@tatrabanka.sk', 'Obrat na ucte', $body));

        $rows = $parser->parse('notify@tatrabanka.sk', 'Obrat', $body);
        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Credit, $rows[0]->direction);
        $this->assertSame('20260042', $rows[0]->variableSymbol);
        $this->assertSame('Client s.r.o.', $rows[0]->counterpartyName);
        $this->assertEqualsWithDelta(150.50, $rows[0]->amount, 0.001);
    }

    #[Test]
    public function parses_debet_notification_as_debit(): void
    {
        $parser = new TatraBankEmailParser;
        $body = 'Debet na ucte (ID=090626/987089-3). Suma: 866,05 EUR. 09.06.2026';

        $rows = $parser->parse('notify@tatrabanka.sk', 'Debet na ucte', $body);
        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
        $this->assertSame('Bankový výdaj', $rows[0]->counterpartyName);
        $this->assertEqualsWithDelta(866.05, $rows[0]->amount, 0.001);
    }

    #[Test]
    public function parses_pos_purchase_as_debit(): void
    {
        $parser = new TatraBankEmailParser;
        $body = 'EUR NAKUP POS. Suma: 18,40 EUR. 09.06.2026';

        $rows = $parser->parse('notify@tatrabanka.sk', 'Obrat', $body);
        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
        $this->assertSame('Platba kartou (POS)', $rows[0]->counterpartyName);
    }

    #[Test]
    public function parses_classic_tatra_bmail_with_payer_name_and_symbols(): void
    {
        $parser = new TatraBankEmailParser;
        $body = <<<'MAIL'
Vazeny klient,

16.1.2015 12:51 bol zostatok Vasho uctu SK9812353347235 zvyseny o 12,31 EUR.
uctovny zostatok: 142,11 EUR

Popis transakcie: CCINT 1100/000000-261426464
Referencia platitela: /VS1234056789/SS9087654321/KS5428175648
Informacia pre prijemcu: Faktura 20260042

S pozdravom
TATRA BANKA, a.s.
MAIL;

        $rows = $parser->parse('notify@tatrabanka.sk', 'Obrat', $body);
        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Credit, $rows[0]->direction);
        $this->assertSame('1234056789', $rows[0]->variableSymbol);
        $this->assertFalse($rows[0]->isVariableSymbolExplicit);
        $this->assertNull($rows[0]->e2eReference);
        $this->assertSame('9087654321', $rows[0]->constantSymbol);
        $this->assertSame('5428175648', $rows[0]->specificSymbol);
        $this->assertSame('1100/000000-261426464', $rows[0]->counterpartyName);
        $this->assertSame('Faktura 20260042', $rows[0]->reference);
        $this->assertEqualsWithDelta(12.31, $rows[0]->amount, 0.001);
    }

    #[Test]
    public function parses_classic_tatra_bmail_with_payer_reference_name(): void
    {
        $parser = new TatraBankEmailParser;
        $body = <<<'MAIL'
12.1.2015 12:11 bol zostatok Vasho uctu SK9812369347235 znizeny o 2,20 EUR.

Popis transakcie: CCINT 1100/000000-261426464
Referencia platitela: Firstname Surname
Informacia pre prijemcu: test-sprava

S pozdravom
MAIL;

        $rows = $parser->parse('notify@tatrabanka.sk', 'Debet', $body);
        $this->assertCount(1, $rows);
        $this->assertSame('Firstname Surname', $rows[0]->counterpartyName);
        $this->assertSame('test-sprava', $rows[0]->reference);
        $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
    }
}
