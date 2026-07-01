<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankImport\CsvBankParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WiseCsvBankParserTest extends TestCase
{
    #[Test]
    public function parses_wise_csv_incoming_row(): void
    {
        $csv = implode("\n", [
            'ID,Status,Direction,Created on,Finished on,Source name,Source amount (after fees),Source currency,Target name,Target amount (after fees),Target currency,Reference',
            'tx-1,COMPLETED,IN,01-06-2026,02-06-2026,Acme Client,250.00,USD,Webium LLC,250.00,USD,Invoice INV-0042',
        ]);

        $rows = (new CsvBankParser)->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Credit, $rows[0]->direction);
        $this->assertSame(250.0, $rows[0]->amount);
        $this->assertSame('USD', $rows[0]->currency);
        $this->assertNull($rows[0]->variableSymbol);
        $this->assertSame('Acme Client', $rows[0]->counterpartyName);
    }
}
