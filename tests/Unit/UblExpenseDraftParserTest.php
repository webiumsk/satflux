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
 <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
 <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>{$supplier}</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
 <cac:AccountingCustomerParty><cac:Party><cac:FinancialAccount><cbc:ID>DE89370400440532013000</cbc:ID></cac:FinancialAccount></cac:Party></cac:AccountingCustomerParty>
 {$payment}{$monetary}{$extra}
</{$documentType}>
XML;
        $draft = (new UblExpenseDraftParser)->parse($xml);
        $this->assertSame($hasAccount, str_contains($draft['internal_note'], 'Supplier bank account:'));
        if ($hasAccount) {
            $this->assertStringContainsString('Supplier bank account: SK6807200002891987426353', $draft['internal_note']);
        }
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
        $payment = '<cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cbc:PaymentID>0000042</cbc:PaymentID><cac:PayeeFinancialAccount><cbc:ID>SK6807200002891987426353</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>';
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
            'short Slovak account despite valid checksum' => ['Synthetic supplier', str_replace('SK6807200002891987426353', 'SK7700000000000', $payment), '', 'Invoice', false],
            'unknown account country despite valid checksum' => ['Synthetic supplier', str_replace('SK6807200002891987426353', 'ZZ6600000000000', $payment), '', 'Invoice', false],
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
  <cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cbc:PaymentID>0000042</cbc:PaymentID></cac:PaymentMeans>
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
        $this->assertSame('0000042', $draft['variable_symbol']);
    }

    #[DataProvider('paymentReferences')]
    public function test_payment_reference_requires_one_supported_seller_instruction(string $payment, ?string $expected): void
    {
        $xml = <<<XML
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
 <cbc:ID>20260001</cbc:ID><cbc:IssueDate>2026-11-17</cbc:IssueDate>
 <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
 <cbc:BuyerReference>12345</cbc:BuyerReference>
 <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Synthetic supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
 <cac:LegalMonetaryTotal><cbc:PayableAmount>200.30</cbc:PayableAmount></cac:LegalMonetaryTotal>
 {$payment}
</Invoice>
XML;
        $draft = (new UblExpenseDraftParser)->parse($xml);
        $this->assertSame($expected, $draft['variable_symbol']);
        $this->assertSame($expected !== null, str_contains($draft['internal_note'], 'Supplier bank account:'));
    }

    /** @return array<string, array{string, string|null}> */
    public static function paymentReferences(): array
    {
        $payment = '<cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cbc:PaymentID>0000042</cbc:PaymentID><cac:PayeeFinancialAccount><cbc:ID>SK6807200002891987426353</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>';

        return [
            'seller reference overrides buyer routing' => [$payment, '0000042'],
            'plain zero is still explicit' => [str_replace('>0000042<', '>0<', $payment), '0'],
            'explicit VS notation' => [str_replace('>0000042<', '>/VS0000042<', $payment), '0000042'],
            'missing ID never uses buyer routing or invoice number' => [str_replace('<cbc:PaymentID>0000042</cbc:PaymentID>', '', $payment), null],
            'alphabetic ID does not concatenate digits' => [str_replace('>0000042<', '>INV-0000042<', $payment), null],
            'long ID is not a Slovak VS' => [str_replace('>0000042<', '>12345678901<', $payment), null],
            'additional instructions not silently dropped' => [str_replace('>0000042<', '>/VS0000042/SS123/KS0308<', $payment), null],
            'duplicate IDs ambiguous even when equal' => [str_replace('</cbc:PaymentID>', '</cbc:PaymentID><cbc:PaymentID>0000042</cbc:PaymentID>', $payment), null],
            'multiple payment instructions ambiguous' => [$payment.$payment, null],
            'nested reference is not the direct payment ID' => [str_replace('<cbc:PaymentID>0000042</cbc:PaymentID>', '<cac:InstructionForCreditorAgent><cbc:PaymentID>0000042</cbc:PaymentID></cac:InstructionForCreditorAgent>', $payment), null],
            'nested payment instruction is ignored' => ['<cac:InvoiceLine>'.$payment.'</cac:InvoiceLine>', null],
            'non-transfer payment reference ignored' => [str_replace('>58<', '>49<', $payment), null],
            'multiple means codes ambiguous' => [str_replace('</cbc:PaymentMeansCode>', '</cbc:PaymentMeansCode><cbc:PaymentMeansCode>30</cbc:PaymentMeansCode>', $payment), null],
        ];
    }

    #[DataProvider('rootPaymentTotals')]
    public function test_payment_financial_terms_are_unambiguous_and_bound_to_the_document_root(
        string $currency,
        string $monetary,
        string $extensionCurrency,
        string $expectedCurrency,
        string $expectedTotal,
        bool $hasAccount,
    ): void {
        $xml = <<<XML
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
 xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
 xmlns:test="urn:satflux:synthetic:extension">
 <ext:UBLExtensions><ext:UBLExtension><ext:ExtensionContent><test:Snapshot>
  <cbc:ID>EXTENSION-ID</cbc:ID><cbc:IssueDate>1900-01-01</cbc:IssueDate><cbc:DueDate>1900-01-02</cbc:DueDate>
  <cbc:DocumentCurrencyCode>{$extensionCurrency}</cbc:DocumentCurrencyCode>
  <cac:LegalMonetaryTotal><cbc:PayableAmount>999.00</cbc:PayableAmount></cac:LegalMonetaryTotal>
  <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Extension supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
 </test:Snapshot></ext:ExtensionContent></ext:UBLExtension></ext:UBLExtensions>
 <cbc:ID>ROOT-001</cbc:ID><cbc:IssueDate>2026-11-17</cbc:IssueDate><cbc:DueDate>2026-11-30</cbc:DueDate>
 {$currency}
 <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Synthetic supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
 {$monetary}
 <cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cbc:PaymentID>0000042</cbc:PaymentID><cac:PayeeFinancialAccount><cbc:ID>SK6807200002891987426353</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>
</Invoice>
XML;
        $draft = (new UblExpenseDraftParser)->parse($xml);
        $this->assertSame('ROOT-001', $draft['external_number']);
        $this->assertSame('2026-11-17', $draft['issue_date']);
        $this->assertSame('2026-11-30', $draft['due_date']);
        $this->assertSame('Synthetic supplier', $draft['title']);
        $this->assertSame('0000042', $draft['variable_symbol']);
        $this->assertSame($expectedCurrency, $draft['currency']);
        $this->assertSame($expectedTotal, $draft['total']);
        $this->assertSame($hasAccount, str_contains($draft['internal_note'], 'Supplier bank account:'));
    }

    /** @return array<string, array{string, string, string, string, string, bool}> */
    public static function rootPaymentTotals(): array
    {
        $currency = '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>';
        $total = '<cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="EUR">10.00</cbc:PayableAmount></cac:LegalMonetaryTotal>';

        return [
            'root EUR amount ignores USD extension' => [$currency, $total, 'USD', 'EUR', '10.00', true],
            'root USD amount never becomes EUR from extension' => [str_replace('EUR', 'USD', $currency), str_replace('EUR', 'USD', $total), 'EUR', 'USD', '10.00', true],
            'missing root currency cannot authorize default EUR' => ['', $total, 'USD', 'EUR', '10.00', false],
            'empty root currency cannot authorize default EUR' => [str_replace('>EUR<', '><', $currency), $total, 'USD', 'EUR', '10.00', false],
            'lowercase root currency not a payment instruction' => [str_replace('EUR', 'eur', $currency), $total, 'USD', 'eur', '10.00', false],
            'malformed root currency' => [str_replace('EUR', 'EU', $currency), $total, 'USD', 'EU', '10.00', false],
            'duplicate root currency even when equal' => [$currency.$currency, $total, 'USD', 'EUR', '10.00', false],
            'duplicate monetary totals even when equal' => [$currency, $total.$total, 'USD', 'EUR', '10.00', false],
            'missing root monetary total ignores extension' => [$currency, '', 'USD', 'EUR', '0.00', false],
            'duplicate root payable amounts' => [$currency, str_replace('</cbc:PayableAmount>', '</cbc:PayableAmount><cbc:PayableAmount currencyID="EUR">10.00</cbc:PayableAmount>', $total), 'USD', 'EUR', '10.00', false],
            'gross fallback is display only' => [$currency, str_replace('PayableAmount', 'TaxInclusiveAmount', $total), 'USD', 'EUR', '10.00', false],
            'zero payable cannot authorize gross fallback' => [$currency, '<cac:LegalMonetaryTotal><cbc:PayableAmount>0</cbc:PayableAmount><cbc:TaxInclusiveAmount>10.00</cbc:TaxInclusiveAmount></cac:LegalMonetaryTotal>', 'USD', 'EUR', '10.00', false],
            'explicit zero prepaid allowed' => [$currency, str_replace('</cac:LegalMonetaryTotal>', '<cbc:PrepaidAmount currencyID="EUR">0.00</cbc:PrepaidAmount></cac:LegalMonetaryTotal>', $total), 'USD', 'EUR', '10.00', true],
            'duplicate zero prepaid amounts ambiguous' => [$currency, str_replace('</cac:LegalMonetaryTotal>', '<cbc:PrepaidAmount>0</cbc:PrepaidAmount><cbc:PrepaidAmount>0</cbc:PrepaidAmount></cac:LegalMonetaryTotal>', $total), 'USD', 'EUR', '10.00', false],
            'duplicate prepaid cannot hide a partial payment' => [$currency, str_replace('</cac:LegalMonetaryTotal>', '<cbc:PrepaidAmount>0</cbc:PrepaidAmount><cbc:PrepaidAmount>1</cbc:PrepaidAmount></cac:LegalMonetaryTotal>', $total), 'USD', 'EUR', '10.00', false],
            'empty prepaid is not explicit zero' => [$currency, str_replace('</cac:LegalMonetaryTotal>', '<cbc:PrepaidAmount /></cac:LegalMonetaryTotal>', $total), 'USD', 'EUR', '10.00', false],
            'prepaid currency mismatch' => [$currency, str_replace('</cac:LegalMonetaryTotal>', '<cbc:PrepaidAmount currencyID="USD">0</cbc:PrepaidAmount></cac:LegalMonetaryTotal>', $total), 'USD', 'EUR', '10.00', false],
            'payable currency mismatch' => [$currency, str_replace('currencyID="EUR"', 'currencyID="USD"', $total), 'USD', 'EUR', '10.00', false],
            'empty payable currency attribute is ambiguous' => [$currency, str_replace('currencyID="EUR"', 'currencyID=""', $total), 'USD', 'EUR', '10.00', false],
            'extra payable precision is not silently truncated for payment' => [$currency, str_replace('10.00', '10.009', $total), 'USD', 'EUR', '10.00', false],
            'comma decimal preserves exact supported amount' => [$currency, str_replace('10.00', '10,50', $total), 'USD', 'EUR', '10.50', true],
        ];
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
