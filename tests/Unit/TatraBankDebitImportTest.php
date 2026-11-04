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

    /** Invalid date/time suffixes must reject with or without VS rather than fall back to a partial date. */
    #[Test]
    #[TestWith(['03.11.2026X'])]
    #[TestWith(['03.11.2026ž'])]
    #[TestWith(['03.11.2026_'])]
    #[TestWith(['03.11.20260'])]
    #[TestWith(['03.11.2026T14:35X'])]
    #[TestWith(['03.11.2026 14:35X'])]
    #[TestWith(['03.11.2026 14:350'])]
    #[TestWith(['03.11.2026 14:3'])]
    #[TestWith(['03.11.2026 123:35'])]
    #[TestWith(['03.11.2026 14:35:1'])]
    #[TestWith(['03.11.2026 14:35:10X'])]
    #[TestWith(['03.11.2026 14:35:61'])]
    #[TestWith(['03.11.2026X. 04.11.2026'])]
    public function malformed_compact_date_tokens_do_not_fall_back_to_a_partial_date(string $date): void
    {
        foreach (['', 'VS: 001234. '] as $symbol) {
            $body = 'Debet. Suma: 123,45 EUR. '.$symbol.$date;
            $this->assertSame([], (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body));
        }
    }

    /** Complete date tokens preserve optional bank time independently of whether the movement includes a VS. */
    #[Test]
    #[TestWith(['03.11.2026', '00:00:00'])]
    #[TestWith(['03.11.2026.', '00:00:00'])]
    #[TestWith(['03.11.2026 14:35', '14:35:00'])]
    #[TestWith(['03.11.2026T14:35', '14:35:00'])]
    #[TestWith(['03.11.2026 14:35:10', '14:35:10'])]
    #[TestWith(['03.11.2026T14:35:10', '14:35:10'])]
    public function complete_compact_dates_preserve_the_optional_bank_time(string $date, string $time): void
    {
        foreach (['', 'VS: 001234. '] as $symbol) {
            $body = 'Debet. Suma: 123,45 EUR. '.$symbol.$date;
            $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body);
            $this->assertCount(1, $rows);
            $this->assertSame('2026-11-03 '.$time, $rows[0]->bookedAt->format('Y-m-d H:i:s'));
        }
    }

    #[Test]
    public function html_only_compact_debit_keeps_its_date_without_a_variable_symbol(): void
    {
        $body = '<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p>';
        $rows = (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body);

        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
        $this->assertSame(123.45, $rows[0]->amount);
        $this->assertSame('2026-11-03', $rows[0]->bookedAt->format('Y-m-d'));
        $this->assertNull($rows[0]->variableSymbol);
    }

    /** Both delegated parsers preserve layout-separated fields and inline numeric content without using remittance values. */
    #[Test]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>VS: 001234</p>', '00:00:00'])]
    #[TestWith(['<div>Debet. Suma: 123,45 EUR.</div><div>03.11.2026</div><div>VS: 001234</div>', '00:00:00'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.<br>03.11.2026<br>VS: 001234</p>', '00:00:00'])]
    #[TestWith(['<P class="notice">Debet. Suma: 123,45 EUR.<BR />03.11.2026<BR data-layout="mail">VS: 001234</P>', '00:00:00'])]
    #[TestWith(['<table><tr><td>Debet. Suma: 123,45 EUR.</td><td>03.11.2026</td></tr><tr><th>VS:</th><td>001234</td></tr></table>', '00:00:00'])]
    #[TestWith(['<p>Debet. Suma: <span>123</span>,<strong>45</strong> EUR. <span>03.</span><b>11.</b><span>2026</span> VS: <span>00</span><strong>1234</strong></p>', '00:00:00'])]
    #[TestWith(['<p title="ignored > 04.11.2026">Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>VS: <p-inline>00</p-inline><span>1234</span></p>', '00:00:00'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>VS: 001234.</p><p>Informacia <span>pre</span> prijemcu: Suma: 999,00 USD. Invoice 04.11.2026</p>', '00:00:00'])]
    #[TestWith(['<p>03.11.2026 14:35 bol zostatok Vasho uctu SK4811000000002944116480 znizeny o 123,45 EUR.</p><p>Referencia platitela: /VS001234/SS/KS</p>', '14:35:00'])]
    public function html_layout_separates_complete_bank_fields_without_splitting_inline_tokens(string $body, string $time): void
    {
        foreach ([new TatraBankEmailParser, new SlspBankEmailParser] as $parser) {
            $rows = $parser->parse('notify@tatrabanka.sk', 'Debet', $body);
            $this->assertCount(1, $rows);
            $this->assertSame(BankTransactionDirection::Debit, $rows[0]->direction);
            $this->assertSame(123.45, $rows[0]->amount);
            $this->assertSame('EUR', $rows[0]->currency);
            $this->assertSame('001234', $rows[0]->variableSymbol);
            $this->assertSame('2026-11-03 '.$time, $rows[0]->bookedAt->format('Y-m-d H:i:s'));
        }
    }

    #[Test]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>VS: 123</p><p>456</p>'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>VS: <span>123</span><b>X</b></p>'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p><span>03.11.2026</span><b>X</b></p>'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>03.11.2026</p><p>04.11.2026</p>'])]
    #[TestWith(['<p>Debet. Suma: 123,45 EUR.</p><p>VS: 001234.</p><p>Informacia pre prijemcu: 03.11.2026</p>'])]
    #[TestWith(['<p>Debet. 03.11.2026.</p><p>VS: 001234.</p><p>Informacia pre prijemcu: Suma: 123,45 EUR</p>'])]
    #[TestWith(['<p title="ignored > 03.11.2026">Debet. Suma: 123,45 EUR.</p><p>VS: 001234.</p>'])]
    public function html_layout_does_not_qualify_ambiguous_tokens_or_remittance_fields(string $body): void
    {
        $this->assertSame([], (new TatraBankEmailParser)->parse('notify@tatrabanka.sk', 'Debet', $body));
    }
}
