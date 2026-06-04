<?php

namespace App\Services\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Support\Invoicing\CompanyAppSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

class BusinessDocumentPdfService
{
    public function __construct(
        protected PayBySquareGenerator $payBySquare,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
        protected BusinessDocumentBtcPayService $btcPayService,
        protected CompanyBrandingService $brandingService,
        protected CompanyPdfFilenameBuilder $pdfFilenameBuilder,
        protected BusinessDocumentIsdocService $isdocService,
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    public function download(BusinessDocument $document): Response
    {
        $filename = $this->pdfFilenameBuilder->build($document);

        AuditLog::log('business_document.pdf_downloaded', 'business_document', $document->id, [
            'company_id' => $document->company_id,
            'number' => $document->number,
        ]);

        return response($this->renderBinary($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function renderBinary(BusinessDocument $document): string
    {
        $document->loadMissing(['company', 'contact', 'lines', 'store']);
        $viewData = $this->viewDataForDocument($document);
        $view = $document->company->jurisdiction === CompanyJurisdiction::Us
            ? 'pdf.business-invoice-us'
            : 'pdf.business-invoice-eu';

        $visualPath = $this->tempPdfPath('pdf-'.uniqid().'.pdf');

        Pdf::view($view, $viewData)->save($visualPath);

        try {
            if ($this->isdocService->supportsEmbedInPdf($document)) {
                $isdocPath = $this->tempPdfPath('pdf-isdoc-'.uniqid().'.pdf');
                try {
                    $this->isdocService->embedIsdocInPdf($visualPath, $document, $isdocPath);

                    return file_get_contents($isdocPath) ?: '';
                } finally {
                    @unlink($isdocPath);
                }
            }

            return file_get_contents($visualPath) ?: '';
        } finally {
            @unlink($visualPath);
        }
    }

    /**
     * @param  Collection<int, BusinessDocument>  $documents
     */
    public function renderMergedBinary(Collection $documents): string
    {
        $pages = [];
        foreach ($documents as $document) {
            $document->loadMissing(['company', 'contact', 'lines', 'store']);
            $pages[] = $this->viewDataForDocument($document);
        }

        $path = $this->tempPdfPath('pdf-merge-'.uniqid().'.pdf');

        Pdf::view('pdf.business-invoices-merged', ['pages' => $pages])->save($path);
        $binary = file_get_contents($path);
        @unlink($path);

        return $binary ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewDataForDocument(BusinessDocument $document): array
    {
        $company = $document->company;
        $bankQr = null;
        if ($document->payment_bank_enabled) {
            $bankQr = $this->payBySquare->generateQrDataUri($company, $document);
        }

        $btcPayQr = null;
        if ($document->payment_btc_enabled && $document->store_id) {
            $document->loadMissing(['store']);
            if (! $document->btcpay_checkout_link) {
                try {
                    $this->btcPayService->syncForDocument($document, forceRefresh: true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $qrTarget = $document->btcpay_checkout_link;
            if (! $qrTarget) {
                $this->paymentTokenService->ensureForDocument($document);
                $qrTarget = $this->paymentTokenService->payUrl($document);
            }

            if ($qrTarget) {
                $btcPayQr = $this->qrPngDataUri($qrTarget);
            }
        }

        app()->setLocale($document->pdf_locale ?: 'sk');

        $canonical = $this->canonicalBuilder->fromDocument($document);
        $settings = CompanyAppSettings::from($company->app_settings);
        $contact = $document->resolvedBuyer();
        $reverseChargeNote = null;
        if ($settings->bool('reverse_charge') && $contact && trim((string) $contact->vat_id) !== '') {
            $reverseChargeNote = (string) ($settings->get('reverse_charge_note')
                ?: __('Reverse charge - VAT to be accounted for by the recipient.'));
        }

        $isUs = $company->jurisdiction === CompanyJurisdiction::Us;

        return [
            'document' => $document,
            'company' => $company,
            'contact' => $contact,
            'lines' => $document->lines,
            'taxBreakdown' => $canonical->taxBreakdown,
            'showSalesTaxColumn' => $isUs && (float) $canonical->taxTotal > 0,
            'reverseChargeNote' => $reverseChargeNote,
            'bankQr' => $bankQr,
            'btcPayQr' => $btcPayQr,
            'logoDataUri' => $this->brandingService->imageDataUri($company->logo_path),
            'signatureStampDataUri' => $this->brandingService->imageDataUri($company->signature_stamp_path),
        ];
    }

    protected function tempPdfPath(string $filename): string
    {
        $candidates = [
            Storage::disk('local')->path('temp'),
            storage_path('app/temp'),
            sys_get_temp_dir().'/satflux-pdf',
        ];

        foreach ($candidates as $dir) {
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                continue;
            }
            if (is_writable($dir)) {
                return rtrim($dir, '/').'/'.$filename;
            }
        }

        return sys_get_temp_dir().'/'.$filename;
    }

    protected function qrPngDataUri(string $data, int $size = 180): ?string
    {
        if (class_exists(\chillerlan\QRCode\QRCode::class)) {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'scale' => max(4, (int) floor($size / 25)),
                'imageBase64' => true,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($options);

            return $qr->render($data);
        }

        $url = 'https://api.qrserver.com/v1/create-qr-code/?'.http_build_query([
            'size' => "{$size}x{$size}",
            'data' => $data,
        ]);
        $png = @file_get_contents($url);
        if ($png === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
