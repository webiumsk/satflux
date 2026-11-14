<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class Pr443FractionalRatePdfTest extends TestCase
{
    use RefreshDatabase;

    public static function rates(): array
    {
        return [
            ['us', 8.875, '8.875%'],
            ['us', 8.8751, '8.8751%'],
            ['us', 23, '23.00%'],
            ['eu_sk', 8.875, '8,875 %'],
            ['eu_sk', 8.8751, '8,8751 %'],
            ['eu_sk', 23, '23 %'],
        ];
    }

    #[DataProvider('rates')]
    public function test_pdf_labels_preserve_fractional_rates_and_ordinary_formatting(string $jurisdiction, float $rate, string $label): void
    {
        config(['laravel-pdf.driver' => 'dompdf']);
        $company = Company::create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Synthetic Supplier',
            'jurisdiction' => $jurisdiction, 'default_currency' => 'EUR',
            'vat_payer' => true, 'vat_status' => 'payer',
            'app_settings' => ['us_sales_tax_provider' => 'manual', 'embed_isdoc_in_pdf' => false],
        ]);
        $tax = round($rate, 2);
        $document = BusinessDocument::create([
            'company_id' => $company->id, 'type' => 'invoice', 'status' => 'issued',
            'number' => 'SYNTHETIC-RATE', 'currency' => 'EUR', 'issue_date' => '2026-11-14',
            'subtotal' => 100, 'tax_total' => $tax, 'total' => 100 + $tax,
            'pdf_locale' => 'en', 'payment_bank_enabled' => false, 'payment_btc_enabled' => false,
        ]);
        BusinessDocumentLine::create([
            'business_document_id' => $document->id, 'sort_order' => 0, 'name' => 'Synthetic service',
            'quantity' => 1, 'unit_price' => 100, 'tax_rate' => $rate, 'line_total' => 100 + $tax,
        ]);
        $document->load(['company', 'contact', 'lines']);
        $service = app(BusinessDocumentPdfService::class);
        $data = (new ReflectionMethod($service, 'viewDataForDocument'))->invoke($service, $document);
        $view = $jurisdiction === 'us' ? 'pdf.business-invoice-us' : 'pdf.business-invoice-eu';
        $html = view($view, $data)->render();
        $this->assertStringContainsString($label, $html);
        if ($jurisdiction === 'eu_sk') {
            // Both the line and the VAT summary must use the same precise rate.
            $this->assertGreaterThanOrEqual(2, substr_count($html, $label));
        }
        $this->assertStringStartsWith('%PDF-', $service->renderBinary($document));
    }
}
