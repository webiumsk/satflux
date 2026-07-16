<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\BankQrEligibility;
use App\Support\Invoicing\QrPngRenderer;
use DateTime;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Trinetus\PayBySquareGenerator\PayBySquareGenerator as TrinetusPayBySquareGenerator;

/**
 * Generates PAY by square payload strings for Slovak/Czech bank QR codes.
 *
 * @see https://www.sbaonline.sk/wp-content/uploads/2020/03/bysquare-PAYspecifications-1.2.0.pdf
 */
class PayBySquareGenerator
{
    public function canGenerate(Company $company, BusinessDocument $document): bool
    {
        // Which STANDARD fits the payer is BankQrGenerator's decision (the
        // issuer jurisdiction no longer gates it - a Swiss company invoicing
        // a Slovak customer legitimately prints PayBySquare).
        return BankQrEligibility::passes($company, $document);
    }

    public function generatePayload(Company $company, BusinessDocument $document): string
    {
        $paymentDate = $document->issue_date?->toDateTime() ?? now()->toDateTime();

        $generator = (new TrinetusPayBySquareGenerator)
            ->setAmount((float) $document->total)
            ->setCurrency(strtoupper((string) $document->currency))
            ->setDate($paymentDate instanceof DateTime ? $paymentDate : DateTime::createFromInterface($paymentDate))
            ->setIban(preg_replace('/\s+/', '', strtoupper((string) $company->iban)))
            ->setBeneficaryName($this->sanitize($company->displayName()))
            ->setNote($this->sanitize((string) ($document->title ?: 'Invoice '.$document->number)));

        $bic = strtoupper(trim((string) ($company->bic ?? '')));
        if ($bic !== '') {
            $generator->setBic($bic);
        }

        $vs = preg_replace('/\D/', '', (string) ($document->variable_symbol ?? $document->number ?? ''));
        if ($vs !== '') {
            $generator->setVariableSymbol($vs);
        }

        $cs = preg_replace('/\D/', '', (string) ($document->constant_symbol ?? ''));
        if ($cs !== '') {
            $generator->setConstantSymbol($cs);
        }

        $ss = preg_replace('/\D/', '', (string) ($document->specific_symbol ?? ''));
        if ($ss !== '') {
            $generator->setSpecificSymbol($ss);
        }

        try {
            return $generator->getOutput();
        } catch (\Throwable $e) {
            Log::error('Pay by square encoding failed', [
                'company_id' => $company->id,
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Pay by square encoding failed. Ensure xz is installed on the server.', 0, $e);
        }
    }

    /**
     * @return string data:image/png;base64,... QR for embedding in PDF
     */
    public function generateQrDataUri(Company $company, BusinessDocument $document, int $size = 200): ?string
    {
        if (! $this->canGenerate($company, $document)) {
            return null;
        }

        $payload = $this->generatePayload($company, $document);
        if ($payload === '') {
            return null;
        }

        return $this->qrPngDataUri($payload, $size);
    }

    protected function qrPngDataUri(string $data, int $size): ?string
    {
        return QrPngRenderer::dataUri($data, $size);
    }

    protected function sanitize(string $value): string
    {
        $value = preg_replace('/[\t\r\n]+/', ' ', $value) ?? $value;

        return trim(mb_substr($value, 0, 70));
    }
}
