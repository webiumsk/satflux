<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\BankQrEligibility;
use App\Support\Invoicing\QrPngRenderer;

/**
 * EPC QR ("BCD" / EPC069-12, version 002) - the SEPA credit transfer QR
 * read by German, Austrian, Dutch, Belgian... banking apps. EUR only by
 * specification; the creditor IBAN may be any SEPA account.
 */
class EpcQrGenerator
{
    public function canGenerate(Company $company, BusinessDocument $document): bool
    {
        if (! BankQrEligibility::passes($company, $document)) {
            return false;
        }

        return strtoupper((string) $document->currency) === 'EUR';
    }

    public function generatePayload(Company $company, BusinessDocument $document): string
    {
        $iban = preg_replace('/\s+/', '', strtoupper((string) $company->iban));
        $bic = strtoupper(trim((string) ($company->bic ?? '')));
        $name = $this->sanitize($company->displayName(), 70);
        $amount = 'EUR'.number_format((float) $document->total, 2, '.', '');
        $remittance = $this->sanitize(
            (string) ($document->variable_symbol ?: ($document->title ?: 'Invoice '.$document->number)),
            140,
        );

        // EPC069-12 order: service tag, version, charset (1 = UTF-8),
        // identification, BIC (optional in v002 within the EEA), name, IBAN,
        // amount, purpose, structured remittance, unstructured remittance.
        return implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            $bic,
            $name,
            $iban,
            $amount,
            '',
            '',
            $remittance,
        ]);
    }

    public function generateQrDataUri(Company $company, BusinessDocument $document, int $size = 200): ?string
    {
        if (! $this->canGenerate($company, $document)) {
            return null;
        }

        return QrPngRenderer::dataUri($this->generatePayload($company, $document), $size);
    }

    protected function sanitize(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\t\r\n]+/', ' ', $value) ?? $value;

        return trim(mb_substr(trim($value), 0, $maxLength));
    }
}
