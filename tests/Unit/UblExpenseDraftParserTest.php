<?php

namespace Tests\Unit;

use App\Services\Invoicing\Efaktura\UblExpenseDraftParser;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UblExpenseDraftParserTest extends TestCase
{
    #[DataProvider('supplierPaymentMetadata')]
    public function test_preserves_only_unambiguous_supplier_payment_metadata(
        string $supplier,
        string $payment,
        string $extra,
        string $documentType,
        bool $hasAccount,
    ): void {
        $monetary = str_contains($extra, '<cac:LegalMonetaryTotal>') ? '' : '<cac:LegalMonetaryTotal><cbc:PayableAmount>200.30</cbc:PayableAmount></cac:LegalMonetaryTotal>';
        $xml = <<<XML
<{$documentType} xmlns="urn:oasis:names:specification:ubl:schema:xsd:{$documentType}-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
 <cbc:IssueDate>2026-11-17</cbc:IssueDate>
 <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>{$supplier}</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
 <cac:AccountingCustomerParty><cac:Party><cac:FinancialAccount><cbc:ID>DE89370400440532013000</cbc:ID></cac:FinancialAccount></cac:Party></cac:AccountingCustomerParty>
 {$payment}{$monetary}{$extra}
</{$documentType}>
XML;
        $draft = (new UblExpenseDraftParser)->parse($xml);
        $this->assertSame($hasAccount, str_contains($draft['internal_note'], 'Supplier bank account: SK6807200002891987426353'));
        $this->assertStringNotContainsString('DE89370400440532013000', $draft['internal_note']);
        $this->assertStringContainsString('Peppol (SAPI-SK).', $draft['internal_note']);
        if (! str_contains($supplier, '&#10;')) {
            $this->assertStringContainsString('Supplier: '.$supplier, $draft['internal_note']);
        } else {
            $this->assertStringNotContainsString('Supplier:', $draft['internal_note']);
        }
    }

    /** @return array<string, array{string, string, string, string, bool}> */
    public static function supplierPaymentMetadata(): array
    {
        $payment = '<cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cac:PayeeFinancialAccount><cbc:ID>SK6807200002891987426353</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>';
        $other = str_replace('SK6807200002891987426353', 'DE89370400440532013000', $payment);

        return [
            'supplier account only' => ['Synthetic supplier', $payment, '', 'Invoice', true],
            'same account repeated instructions omitted' => ['Synthetic supplier', $payment.$payment, '', 'Invoice', false],
            'customer account never selected' => ['Synthetic supplier', '', '', 'Invoice', false],
            'distinct payment accounts ambiguous' => ['Synthetic supplier', $payment.$other, '', 'Invoice', false],
            'explicit factoring payee omitted' => ['Synthetic supplier', $payment, '<cac:PayeeParty><cac:PartyName><cbc:Name>Synthetic factor</cbc:Name></cac:PartyName></cac:PayeeParty>', 'Invoice', false],
            'different account beneficiary omitted' => ['Synthetic supplier', str_replace('</cac:PayeeFinancialAccount>', '<cbc:Name>Synthetic factor</cbc:Name></cac:PayeeFinancialAccount>', $payment), '', 'Invoice', false],
            'non-credit payment omitted' => ['Synthetic supplier', str_replace('>58<', '>49<', $payment), '', 'Invoice', false],
            'invalid account checksum' => ['Synthetic supplier', str_replace('SK6807200002891987426353', 'SK6807200002891987426354', $payment), '', 'Invoice', false],
            'account line injection rejected' => ['Synthetic supplier', str_replace('SK6807200002891987426353', 'SK6807200002891987426353&#10;Supplier: Other', $payment), '', 'Invoice', false],
            'supplier line injection rejected' => ['Synthetic supplier&#10;Supplier bank account: Other', $payment, '', 'Invoice', false],
            'credit note never payable' => ['Synthetic supplier', $payment, '', 'CreditNote', false],
            'prepaid document omitted' => ['Synthetic supplier', $payment, '<cac:LegalMonetaryTotal><cbc:PrepaidAmount>1.00</cbc:PrepaidAmount></cac:LegalMonetaryTotal>', 'Invoice', false],
            'zero payable does not pay gross fallback' => ['Synthetic supplier', $payment, '<cac:LegalMonetaryTotal><cbc:PayableAmount>0</cbc:PayableAmount><cbc:TaxInclusiveAmount>200.30</cbc:TaxInclusiveAmount></cac:LegalMonetaryTotal>', 'Invoice', false],
            'negative payable does not pay gross fallback' => ['Synthetic supplier', $payment, '<cac:LegalMonetaryTotal><cbc:PayableAmount>-1.00</cbc:PayableAmount><cbc:TaxInclusiveAmount>200.30</cbc:TaxInclusiveAmount></cac:LegalMonetaryTotal>', 'Invoice', false],
        ];
    }

    #[DataProvider('signedDocumentTotals')]
    public function test_preserves_the_economic_sign_of_document_totals(
        string $documentType,
        string $amountElement,
        string $sourceAmount,
        string $expectedTotal,
        string $expectedDocumentType,
    ): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$documentType} xmlns="urn:oasis:names:specification:ubl:schema:xsd:{$documentType}-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>SYNTHETIC-SIGNED-TOTAL</cbc:ID>
  <cbc:IssueDate>2026-06-01</cbc:IssueDate>
  <cac:LegalMonetaryTotal>
    <cbc:{$amountElement} currencyID="EUR">{$sourceAmount}</cbc:{$amountElement}>
  </cac:LegalMonetaryTotal>
</{$documentType}>
XML;

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame($expectedTotal, $draft['total']);
        $this->assertSame($expectedDocumentType, $draft['document_type']);
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function signedDocumentTotals(): array
    {
        return [
            'positive credit note reduces expense' => ['CreditNote', 'PayableAmount', '42.50', '-42.50', 'credit_note'],
            'negative credit note reverses negative invoice' => ['CreditNote', 'PayableAmount', '-42.50', '42.50', 'credit_note'],
            'zero credit note stays zero' => ['CreditNote', 'PayableAmount', '0.00', '0.00', 'credit_note'],
            'negative invoice keeps its sign' => ['Invoice', 'PayableAmount', '-42.50', '-42.50', 'invoice'],
            'positive credit note uses tax inclusive fallback' => ['CreditNote', 'TaxInclusiveAmount', '42.50', '-42.50', 'credit_note'],
            'negative credit note uses tax inclusive fallback' => ['CreditNote', 'TaxInclusiveAmount', '-42.50', '42.50', 'credit_note'],
        ];
    }

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

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame('INV-2026-001', $draft['external_number']);
        $this->assertSame('Dodávateľ s.r.o.', $draft['title']);
        $this->assertSame('2026-06-01', $draft['issue_date']);
        $this->assertSame('2026-06-15', $draft['due_date']);
        $this->assertSame('123.00', $draft['total']);
        $this->assertSame('EUR', $draft['currency']);
        $this->assertSame('20260615001', $draft['variable_symbol']);
    }

    public function test_parses_comma_decimal_amounts(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>INV-COMMA</cbc:ID>
  <cbc:IssueDate>2026-06-01</cbc:IssueDate>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="EUR">123,45</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>
XML;

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame('123.45', $draft['total']);
    }

    public function test_invalid_amount_falls_back_to_zero(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>INV-BAD</cbc:ID>
  <cbc:IssueDate>2026-06-01</cbc:IssueDate>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="EUR">abc</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>
XML;

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame('0.00', $draft['total']);
    }

    public function test_missing_issue_date_uses_today_fallback(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>INV-NO-DATE</cbc:ID>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="EUR">10.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>
XML;

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame('2026-06-10', $draft['issue_date']);
        $this->assertNull($draft['delivery_date']);

        Carbon::setTestNow();
    }

    public function test_malformed_issue_date_uses_today_fallback(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>INV-BAD-DATE</cbc:ID>
  <cbc:IssueDate>not-a-date</cbc:IssueDate>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="EUR">10.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>
XML;

        $draft = (new UblExpenseDraftParser)->parse($xml);

        $this->assertSame('2026-06-10', $draft['issue_date']);
        $this->assertNull($draft['delivery_date']);

        Carbon::setTestNow();
    }
}
