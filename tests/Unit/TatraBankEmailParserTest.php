<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TatraBankEmailParserTest extends TestCase
{
    private const RECIPIENT_IBAN = 'SK4811000000002944116480';

    private const E2E_REFERENCE = 'QR-ab29e346f1d841c8a95a63d857490818';

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
    public function classic_credit_carries_observed_recipient_and_explicit_settlement_evidence(): void
    {
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE."\n"
            .'Informacia pre prijemcu: Invoice payment';

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertSame(self::RECIPIENT_IBAN, $row->recipientIban);
        $this->assertNull($row->counterpartyIban);
        $this->assertTrue($row->isExplicitCredit);
        $this->assertTrue($row->hasExplicitCurrency);
        $this->assertSame(self::E2E_REFERENCE, $row->e2eReference);
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
    public function e2e_reference_comes_from_the_transaction_field_instead_of_arbitrary_message_text(): void
    {
        $otherReference = 'QR-00000000000000000000000000000001';
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.self::E2E_REFERENCE."\n"
            .'Informacia pre prijemcu: See unrelated reference '.$otherReference;

        $row = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Obrat', $body)[0];

        $this->assertSame(self::E2E_REFERENCE, $row->e2eReference);
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
