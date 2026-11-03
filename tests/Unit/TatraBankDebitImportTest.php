<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Services\Invoicing\BankImport\SlspBankEmailParser;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TatraBankDebitImportTest extends TestCase
{
    #[Test]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. 03.11.2026', '001234'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. KS: 0308. 03.11.2026', '001234'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. 03.11.2026T14:35', '001234'])]
    #[TestWith(['Debet. Suma: 1 123,45 EUR. VS: 001234. 03.11.2026', '001234'])]
    #[TestWith(['Debet. Suma: -123,45 EUR. 03.11.2026', null])]
    #[TestWith(['Debet. Suma: 123,45 EUR. 03.11.2026. Informacia pre prijemcu: Invoice 001234', null])]
    #[TestWith(["03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nReferencia platitela: /VS001234/SS/KS", '001234'])]
    #[TestWith(["03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nInformacia pre prijemcu: Invoice 001234", null])]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 001234. 03.11.2026\nInformacia pre prijemcu: Invoice dated 04.11.2026", '001234'])]
    public function explicit_debit_fields_are_preserved_without_inferred_symbols(string $body, ?string $symbol): void
    {
        $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body);
        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
        $this->assertSame('2026-11-03', $rows[0]->bookedAt->format('Y-m-d'));
        $this->assertSame('EUR', $rows[0]->currency);
        $this->assertSame($symbol, $rows[0]->variableSymbol);
    }

    #[Test]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234'])]
    #[TestWith(['Debet. Suma: 123,45. VS: 001234. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,450 EUR. VS: 001234. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 1 23,45 EUR. VS: 001234. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. 31.02.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. 03.11.2026 24:10'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: invalid. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 12.34. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 12abc3. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 12345678901. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 123 456. 03.11.2026'])]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 123\t456. 03.11.2026"])]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 123\u{00a0}456. 03.11.2026"])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 123. 456. 03.11.2026'])]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 123.\t456. 03.11.2026"])]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 123.\u{00a0}456. 03.11.2026"])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 123. 03.11.2026X'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. VS: 5678. 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. 03.11.2026. 04.11.2026'])]
    #[TestWith(["31.02.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nSuma: 123,45 EUR. 03.11.2026. VS: 001234"])]
    #[TestWith(["03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,450 EUR.\nSuma: 123,45 EUR. VS: 001234"])]
    #[TestWith(["03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45.\nSuma: 123,45 EUR. VS: 001234"])]
    #[TestWith(["03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nReferencia platitela: /VSinvalid/SS/KS"])]
    #[TestWith(["Unsupported preamble\n03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.\nSuma: 123,45 EUR. VS: 001234"])]
    public function incomplete_or_ambiguous_debits_are_not_importable(string $body): void
    {
        $this->assertSame([], (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body));
        $this->assertSame([], (new SlspBankEmailParser)->parse('notify@slsp.sk', 'Debet', $body));
    }

    #[Test]
    #[TestWith(["Debet. Suma: 123,45 EUR. VS: 001234.\nInformacia pre prijemcu: 03.11.2026"])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. Informacia pre prijemcu: 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. Referencia platitela: 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. Protistrana: Supplier 03.11.2026'])]
    #[TestWith(['Debet. Suma: 123,45 EUR. VS: 001234. Popis transakcie: Invoice 03.11.2026'])]
    #[TestWith(['Debet. 03.11.2026. VS: 001234. Informacia pre prijemcu: Suma: 123,45 EUR'])]
    public function remittance_fields_cannot_supply_compact_bank_amount_or_date(string $body): void
    {
        $this->assertSame([], (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body));
    }
}
