<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TatraBankEmailParserTest extends TestCase
{
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
