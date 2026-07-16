<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\BankQrEligibility;
use App\Support\Invoicing\QrPngRenderer;

/**
 * Swiss QR-Rechnung payload (SPC v2.0) for CH/LI payers. Hard rules of the
 * standard: the creditor account must be a CH/LI IBAN and the currency CHF
 * or EUR. v1 renders the QR code in the invoice's QR slot; the formal
 * QR-bill payment-part layout (Swiss cross, separation line) is a follow-up.
 */
class SwissQrGenerator
{
    public function canGenerate(Company $company, BusinessDocument $document): bool
    {
        if (! BankQrEligibility::passes($company, $document)) {
            return false;
        }

        if (! in_array(strtoupper((string) $document->currency), ['CHF', 'EUR'], true)) {
            return false;
        }

        $iban = $this->iban($company);

        return str_starts_with($iban, 'CH') || str_starts_with($iban, 'LI');
    }

    public function generatePayload(Company $company, BusinessDocument $document): string
    {
        $creditorCountry = strtoupper(trim((string) $company->country)) ?: 'CH';
        $addressLine2 = trim(trim((string) $company->postal_code).' '.trim((string) $company->city));

        $lines = [
            'SPC',
            '0200',
            '1',
            $this->iban($company),
            // Creditor, combined address type (K): name, 2 address lines,
            // empty postal code + town slots, country.
            'K',
            $this->sanitize($company->displayName(), 70),
            $this->sanitize((string) $company->street, 70),
            $this->sanitize($addressLine2, 70),
            '',
            '',
            $creditorCountry,
        ];

        // Ultimate creditor block: unused (7 empty fields per spec).
        $lines = array_merge($lines, array_fill(0, 7, ''));

        $lines[] = number_format((float) $document->total, 2, '.', '');
        $lines[] = strtoupper((string) $document->currency);

        // Ultimate debtor block: unused (7 empty fields).
        $lines = array_merge($lines, array_fill(0, 7, ''));

        // Reference: NON needs no QR-IBAN; the unstructured message carries
        // the document reference for the payer.
        $lines[] = 'NON';
        $lines[] = '';
        $lines[] = $this->sanitize(
            (string) ($document->title ?: 'Invoice '.$document->number),
            140,
        );
        $lines[] = 'EPD';

        return implode("\n", $lines);
    }

    public function generateQrDataUri(Company $company, BusinessDocument $document, int $size = 200): ?string
    {
        if (! $this->canGenerate($company, $document)) {
            return null;
        }

        return QrPngRenderer::dataUri($this->generatePayload($company, $document), $size);
    }

    protected function iban(Company $company): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $company->iban));
    }

    protected function sanitize(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\t\r\n]+/', ' ', $value) ?? $value;

        return trim(mb_substr(trim($value), 0, $maxLength));
    }
}
