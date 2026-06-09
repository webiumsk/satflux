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
    }
}
