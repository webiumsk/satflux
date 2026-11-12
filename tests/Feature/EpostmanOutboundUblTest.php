<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Services\Invoicing\EphemeralDocumentFactory;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EpostmanOutboundUblTest extends TestCase
{
    public static function documentTypes(): array
    {
        return [['invoice', 'Invoice', 'InvoiceTypeCode', '380'], ['credit_note', 'CreditNote', 'CreditNoteTypeCode', '381']];
    }

    #[DataProvider('documentTypes')]
    public function test_outbound_xml_respects_the_ubl_document_sequence(string $type, string $root, string $codeElement, string $code): void
    {
        $xml = $this->render($type);
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->assertSame($root, $dom->documentElement->localName);

        // OASIS UBL 2.1 maindoc/UBL-{Invoice,CreditNote}-2.1.xsd:
        // TypeCode and Note precede DocumentCurrencyCode and BuyerReference.
        $headers = [];
        foreach ($dom->documentElement->childNodes as $node) {
            if ($node->namespaceURI === 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2') {
                $headers[] = $node->localName;
            }
        }
        $expected = ['CustomizationID', 'ProfileID', 'ID', 'IssueDate'];
        if ($type === 'invoice') {
            $expected[] = 'DueDate';
        }
        $this->assertSame(array_merge($expected, [$codeElement, 'Note', 'DocumentCurrencyCode', 'BuyerReference']), $headers);
        $this->assertSame($code, $xpath->evaluate('string(/*/cbc:'.$codeElement.')'));
        $this->assertSame('EUR', $xpath->evaluate('string(/*/cbc:DocumentCurrencyCode)'));
        $this->assertSame('123.00', $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)'));

        // Optional offline full-XSD integration check, using unmodified official
        // OASIS schemas mounted by the disposable validation environment.
        $schemaDirectory = getenv('EPOSTMAN_TEST_UBL_SCHEMA_DIR');
        if (is_string($schemaDirectory) && $schemaDirectory !== '') {
            $this->assertTrue($dom->schemaValidate($schemaDirectory.'/maindoc/UBL-'.$root.'-2.1.xsd'));
        }
    }

    #[DataProvider('documentTypes')]
    public function test_line_description_precedes_name_in_the_ubl_item(string $type, string $root, string $codeElement, string $code): void
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->render($type), LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $names = [];
        foreach ($xpath->query('/*/*/cac:Item/*') as $node) {
            $names[] = $node->localName;
        }
        // OASIS UBL 2.1 common/UBL-CommonAggregateComponents-2.1.xsd ItemType.
        $this->assertSame(['Description', 'Name', 'ClassifiedTaxCategory'], $names);
    }

    #[DataProvider('documentTypes')]
    public function test_partial_payment_preserves_the_peppol_payable_balance(string $type, string $root, string $codeElement, string $code): void
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->render($type, 10), LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $prefix = '/*/cac:LegalMonetaryTotal/';
        $this->assertSame('123.00', $xpath->evaluate('string('.$prefix.'cbc:TaxInclusiveAmount)'));
        $this->assertSame('10.00', $xpath->evaluate('string('.$prefix.'cbc:PrepaidAmount)'));
        $this->assertSame('113.00', $xpath->evaluate('string('.$prefix.'cbc:PayableAmount)'));

        // Peppol BR-CO-16: payable = tax-inclusive total - paid amount.
        $this->assertEqualsWithDelta(
            (float) $xpath->evaluate('string('.$prefix.'cbc:TaxInclusiveAmount)') - (float) $xpath->evaluate('string('.$prefix.'cbc:PrepaidAmount)'),
            (float) $xpath->evaluate('string('.$prefix.'cbc:PayableAmount)'),
            0.00001,
        );
        $schemaDirectory = getenv('EPOSTMAN_TEST_UBL_SCHEMA_DIR');
        if (is_string($schemaDirectory) && $schemaDirectory !== '') {
            $this->assertTrue($dom->schemaValidate($schemaDirectory.'/maindoc/UBL-'.$root.'-2.1.xsd'));
        }
    }

    #[DataProvider('documentTypes')]
    public function test_party_legal_names_do_not_require_a_registration_identifier(string $type, string $root, string $codeElement, string $code): void
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($this->render($type, 0, false), LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        // Peppol BR-06/BR-07 use RegistrationName; PartyName is a trading name.
        $seller = '/*/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity';
        $buyer = '/*/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity';
        $this->assertSame('Synthetic issuer', $xpath->evaluate('string('.$seller.'/cbc:RegistrationName)'));
        $this->assertSame('Synthetic buyer', $xpath->evaluate('string('.$buyer.'/cbc:RegistrationName)'));
        $this->assertSame(0.0, $xpath->evaluate('count('.$seller.'/cbc:CompanyID)'));
        $this->assertSame(0.0, $xpath->evaluate('count('.$buyer.'/cbc:CompanyID)'));
        $schemaDirectory = getenv('EPOSTMAN_TEST_UBL_SCHEMA_DIR');
        if (is_string($schemaDirectory) && $schemaDirectory !== '') {
            $this->assertTrue($dom->schemaValidate($schemaDirectory.'/maindoc/UBL-'.$root.'-2.1.xsd'));
        }
    }

    private function render(string $type, float $amountPaid = 0, bool $withRegistrationNumber = true): string
    {
        $company = new Company([
            'legal_name' => 'Synthetic issuer', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR', 'registration_number' => $withRegistrationNumber ? '12345679' : null,
            'tax_id' => '1234567890', 'vat_number' => 'SK1234567890',
            'country' => 'SK', 'street' => 'Test 1', 'city' => 'Test', 'postal_code' => '00000',
            'vat_payer' => true, 'vat_status' => 'payer', 'vat_rate_default' => 23, 'app_settings' => [],
        ]);
        $company->id = '00000000-0000-4000-8000-000000000001';
        $document = app(EphemeralDocumentFactory::class)->document($company, [
            'document' => [
                'type' => $type, 'status' => BusinessDocumentStatus::Issued->value,
                'number' => 'SYNTHETIC-001', 'issue_date' => '2026-11-12', 'due_date' => '2026-11-26',
                'currency' => 'EUR', 'variable_symbol' => '123', 'note_footer' => 'Synthetic note',
                'amount_paid' => $amountPaid,
            ],
            'contact' => [
                'name' => 'Synthetic buyer', 'tax_id' => '0987654321', 'country' => 'SK',
                'street' => 'Test 2', 'city' => 'Test', 'postal_code' => '00000',
            ],
            'lines' => [[
                'name' => 'Synthetic service', 'description' => 'Synthetic description',
                'quantity' => 1, 'unit' => 'ks', 'unit_price' => 100, 'tax_rate' => 23,
            ]],
        ]);

        return app(BusinessDocumentUblService::class)->xml($document, auditDownload: false);
    }
}
