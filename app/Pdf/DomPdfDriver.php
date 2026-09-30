<?php

namespace App\Pdf;

use Dompdf\Dompdf;
use Spatie\LaravelPdf\Drivers\DomPdfDriver as SpatieDomPdfDriver;
use Spatie\LaravelPdf\PdfOptions;

/**
 * Dompdf with Satflux's invoice footer (brand line + "Page X/Y").
 *
 * The footer used to be an inline <script type="text/php"> block, which
 * required Dompdf's isPhpEnabled: any HTML reaching the renderer could then
 * execute PHP. Views now emit an inert <meta name="satflux-pdf-footer">
 * marker with the translated strings and the footer is drawn here, after
 * rendering, through the canvas API. PHP in HTML stays disabled.
 */
class DomPdfDriver extends SpatieDomPdfDriver
{
    public const FOOTER_MARKER = 'satflux-pdf-footer';

    public function generatePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): string
    {
        $dompdf = $this->buildDompdf($html, $headerHtml, $footerHtml, $options);

        $dompdf->render();

        $footer = $this->footerStrings($html);
        if ($footer !== null) {
            $this->drawFooter($dompdf, $footer['brand'], $footer['page']);
        }

        return $dompdf->output();
    }

    /**
     * @return array{brand: string, page: string}|null
     */
    protected function footerStrings(string $html): ?array
    {
        $pattern = '/<meta\s+name="'.self::FOOTER_MARKER.'"\s+content="([^"]*)"/i';
        if (! preg_match($pattern, $html, $match)) {
            return null;
        }

        $data = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (! is_array($data) || ! is_string($data['brand'] ?? null) || ! is_string($data['page'] ?? null)) {
            return null;
        }

        return ['brand' => $data['brand'], 'page' => $data['page']];
    }

    protected function drawFooter(Dompdf $dompdf, string $brandText, string $pageLabel): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans');
        if (! $font) {
            return;
        }

        $pageWidth = $canvas->get_width();
        $y = $canvas->get_height() - 40;

        $brandSize = 6;
        $brandWidth = $fontMetrics->getTextWidth($brandText, $font, $brandSize);
        $canvas->page_text(($pageWidth - $brandWidth) / 2, $y, $brandText, $font, $brandSize, [0.42, 0.45, 0.5]);

        $pageSize = 5.5;
        $sampleWidth = $fontMetrics->getTextWidth($pageLabel.' 99/99', $font, $pageSize);
        $canvas->page_text(
            $pageWidth - 32 - $sampleWidth,
            $y,
            $pageLabel.' {PAGE_NUM}/{PAGE_COUNT}',
            $font,
            $pageSize,
            [0.61, 0.64, 0.69],
        );
    }
}
