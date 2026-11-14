<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Services\Invoicing\EphemeralDocumentFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceQuantityDeductionUblTest extends TestCase
{
    public static function invalidRawDeductions(): array
    {
        return [
            'quantity lost by decimal cast' => [-1e-20, 15, false],
            'price lost by decimal cast' => [-1, 1e-20, false],
            'stock link on imported line' => [-1, 15, true],
        ];
    }

    #[DataProvider('invalidRawDeductions')]
    public function test_raw_deduction_cannot_bypass_export_eligibility(float $quantity, float $price, bool $stockLinked): void
    {
        $company = new Company([
            'legal_name' => 'Synthetic supplier', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR', 'vat_payer' => false, 'vat_status' => 'none', 'app_settings' => [],
        ]);
        $company->id = '00000000-0000-4000-8000-000000000001';
        $document = app(EphemeralDocumentFactory::class)->document($company, [
            'document' => ['type' => 'invoice', 'status' => 'issued'],
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
                ['name' => 'Deduction', 'quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => 0],
            ],
        ]);
        if ($stockLinked) {
            $document->lines[1]->company_stock_item_id = '00000000-0000-4000-8000-000000000002';
        }

        $this->expectException(ValidationException::class);
        app(BusinessDocumentUblService::class)->xml($document, auditDownload: false);
    }

    public static function unsupportedDiscounts(): array
    {
        return ['document discount' => [10, 0], 'line discount' => [0, 10]];
    }

    #[DataProvider('unsupportedDiscounts')]
    public function test_quantity_deduction_with_discount_cannot_emit_inconsistent_ubl(int $documentDiscount, int $lineDiscount): void
    {
        $company = new Company([
            'legal_name' => 'Synthetic supplier', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR', 'vat_payer' => false, 'vat_status' => 'none', 'app_settings' => [],
        ]);
        $company->id = '00000000-0000-4000-8000-000000000001';
        $document = app(EphemeralDocumentFactory::class)->document($company, [
            'document' => ['type' => 'invoice', 'status' => 'issued', 'discount_percent' => $documentDiscount],
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
                ['name' => 'Deduction', 'quantity' => -1, 'unit_price' => 15, 'tax_rate' => 0, 'line_discount_percent' => $lineDiscount],
            ],
        ]);

        $this->expectException(ValidationException::class);
        app(BusinessDocumentUblService::class)->xml($document, auditDownload: false);
    }

    public static function invoiceAmounts(): array
    {
        return [
            'VAT payer' => [true, 0.0, '19.55', '104.55', '104.55'],
            'non-payer' => [false, 0.0, '0.00', '85.00', '85.00'],
            'partly paid' => [true, 10.0, '19.55', '104.55', '94.55'],
        ];
    }

    #[DataProvider('invoiceAmounts')]
    public function test_quantity_deduction_keeps_positive_prices_and_consistent_ubl_amounts(
        bool $vatPayer,
        float $amountPaid,
        string $tax,
        string $total,
        string $payable,
    ): void {
        $company = new Company([
            'legal_name' => 'Synthetic supplier', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR', 'tax_id' => '1234567890',
            'vat_number' => $vatPayer ? 'SK1234567890' : null,
            'country' => 'SK', 'street' => 'Test 1', 'city' => 'Test', 'postal_code' => '00000',
            'vat_payer' => $vatPayer, 'vat_status' => $vatPayer ? 'payer' : 'none',
            'vat_rate_default' => 23, 'app_settings' => [],
        ]);
        $company->id = '00000000-0000-4000-8000-000000000001';
        $document = app(EphemeralDocumentFactory::class)->document($company, [
            'document' => [
                'type' => 'invoice', 'status' => 'issued', 'number' => 'SYNTHETIC-DEDUCTION',
                'issue_date' => '2026-11-13', 'due_date' => '2026-11-27',
                'currency' => 'EUR', 'amount_paid' => $amountPaid,
            ],
            'contact' => [
                'name' => 'Synthetic buyer', 'tax_id' => '0987654321', 'country' => 'SK',
                'street' => 'Test 2', 'city' => 'Test', 'postal_code' => '00000',
            ],
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit' => 'ks', 'unit_price' => 100, 'tax_rate' => 23],
                ['name' => 'Deduction', 'quantity' => -1, 'unit' => 'ks', 'unit_price' => 15, 'tax_rate' => 23],
            ],
        ]);

        $xml = app(BusinessDocumentUblService::class)->xml($document, auditDownload: false);
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->assertSame('Invoice', $dom->documentElement->localName);
        $this->assertSame('-1', $xpath->evaluate('string(/*/cac:InvoiceLine[2]/cbc:InvoicedQuantity)'));
        $this->assertSame('15.00', $xpath->evaluate('string(/*/cac:InvoiceLine[2]/cac:Price/cbc:PriceAmount)'));
        $this->assertSame('-15.00', $xpath->evaluate('string(/*/cac:InvoiceLine[2]/cbc:LineExtensionAmount)'));
        $this->assertSame('85.00', $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:LineExtensionAmount)'));
        $this->assertSame($tax, $xpath->evaluate('string(/*/cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('85.00', $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount)'));
        $this->assertSame($tax, $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount)'));
        $this->assertSame($total, $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)'));
        $this->assertSame($payable, $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)'));

        // Peppol BR-27, R120 and BR-CO-16: nonnegative item prices,
        // quantity times price equals line net, and prepaid reduces payable.
        foreach ($xpath->query('/*/cac:InvoiceLine') as $line) {
            $quantity = (float) $xpath->evaluate('string(cbc:InvoicedQuantity)', $line);
            $price = (float) $xpath->evaluate('string(cac:Price/cbc:PriceAmount)', $line);
            $lineNet = (float) $xpath->evaluate('string(cbc:LineExtensionAmount)', $line);
            $this->assertGreaterThanOrEqual(0, $price);
            $this->assertEqualsWithDelta($quantity * $price, $lineNet, 0.00001);
        }
        $this->assertEqualsWithDelta((float) $total - $amountPaid, (float) $payable, 0.00001);
        $this->assertGreaterThan(0, (float) $payable);

        $schemaDirectory = getenv('EPOSTMAN_TEST_UBL_SCHEMA_DIR');
        if (is_string($schemaDirectory) && $schemaDirectory !== '') {
            $this->assertTrue($dom->schemaValidate($schemaDirectory.'/maindoc/UBL-Invoice-2.1.xsd'));
        }
    }
}
