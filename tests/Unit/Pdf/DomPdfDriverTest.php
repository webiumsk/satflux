<?php

namespace Tests\Unit\Pdf;

use App\Pdf\DomPdfDriver;
use Smalot\PdfParser\Parser;
use Spatie\LaravelPdf\PdfOptions;
use Tests\TestCase;

class DomPdfDriverTest extends TestCase
{
    private function render(string $body): string
    {
        $driver = new DomPdfDriver(config('laravel-pdf.dompdf', []));
        $pdf = $driver->generatePdf("<html><head></head><body>{$body}</body></html>", null, null, new PdfOptions);

        return (new Parser)->parseContent($pdf)->getText();
    }

    public function test_inline_php_in_html_is_never_executed(): void
    {
        unset($GLOBALS['satflux_pdf_php_ran']);

        $this->render('<p>Hello</p><script type="text/php">$GLOBALS["satflux_pdf_php_ran"] = true;</script>');

        $this->assertArrayNotHasKey('satflux_pdf_php_ran', $GLOBALS);
    }

    public function test_footer_marker_draws_brand_and_page_numbers(): void
    {
        $marker = view('pdf.partials.business-invoice-footer-brand-script')->render();

        $text = $this->render('<p>Invoice body</p>'.$marker);

        $this->assertStringContainsString('Invoice body', $text);
        $this->assertStringContainsString(__('Created with SATFLUX.io'), $text);
        $this->assertStringContainsString(__('Page').' 1/1', $text);
    }

    public function test_pages_without_marker_get_no_footer(): void
    {
        $text = $this->render('<p>Report</p>');

        $this->assertStringNotContainsString(__('Created with SATFLUX.io'), $text);
    }
}
