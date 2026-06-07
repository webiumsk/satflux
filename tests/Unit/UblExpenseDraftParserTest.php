<?php

namespace Tests\Unit;

use App\Services\Invoicing\Efaktura\UblExpenseDraftParser;
use PHPUnit\Framework\TestCase;

class UblExpenseDraftParserTest extends TestCase
{
    public function test_parses_supplier_invoice_fields_from_ubl(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>INV-2026-001</cbc:ID>
  <cbc:IssueDate>2026-06-01</cbc:IssueDate>
  <cbc:DueDate>2026-06-15</cbc:DueDate>
  <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
  <cbc:BuyerReference>20260615001</cbc:BuyerReference>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyName><cbc:Name>Dodávateľ s.r.o.</cbc:Name></cac:PartyName>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="EUR">123.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>
XML;

        $draft = app(UblExpenseDraftParser::class)->parse($xml);

        $this->assertSame('INV-2026-001', $draft['external_number']);
        $this->assertSame('Dodávateľ s.r.o.', $draft['title']);
        $this->assertSame('2026-06-01', $draft['issue_date']);
        $this->assertSame('2026-06-15', $draft['due_date']);
        $this->assertSame('123.00', $draft['total']);
        $this->assertSame('EUR', $draft['currency']);
        $this->assertSame('20260615001', $draft['variable_symbol']);
    }
}
