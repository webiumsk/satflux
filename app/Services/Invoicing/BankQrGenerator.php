<?php

namespace App\Services\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\JurisdictionRules;

/**
 * Picks the bank-payment QR standard by the PAYER, not the issuer: the QR
 * is scanned by the customer's banking app, so the buyer's country decides
 * (billing country is a best guess - the per-document pdf_bank_qr choice
 * overrides it when the merchant knows better). The issuer side only
 * constrains feasibility (currency, creditor IBAN type).
 */
class BankQrGenerator
{
    /** Countries whose banking apps commonly read the EPC ("BCD") QR. */
    protected const EPC_COUNTRIES = [
        'DE', 'AT', 'NL', 'BE', 'LU', 'FI', 'IE', 'FR', 'IT', 'ES', 'PT',
        'GR', 'CY', 'MT', 'SI', 'HR', 'EE', 'LV', 'LT', 'PL', 'HU', 'RO',
        'BG', 'DK', 'SE', 'IS', 'NO', 'MC', 'SM', 'AD',
    ];

    public function __construct(
        protected PayBySquareGenerator $payBySquare,
        protected EpcQrGenerator $epc,
        protected SwissQrGenerator $swiss,
    ) {}

    public function generateQrDataUri(Company $company, BusinessDocument $document, int $size = 200): ?string
    {
        return match ($this->selectStandard($company, $document)) {
            'paybysquare' => $this->payBySquare->generateQrDataUri($company, $document, $size),
            'epc' => $this->epc->generateQrDataUri($company, $document, $size),
            'swiss' => $this->swiss->generateQrDataUri($company, $document, $size),
            default => null,
        };
    }

    /**
     * Which standard the invoice gets: the explicit per-document choice wins
     * (still feasibility-checked - a forced Swiss QR without a CH/LI IBAN
     * yields no QR rather than an unscannable payload), otherwise the
     * payer-country matrix decides.
     *
     * @return 'paybysquare'|'epc'|'swiss'|null
     */
    public function selectStandard(Company $company, BusinessDocument $document): ?string
    {
        $choice = strtolower(trim((string) ($document->pdf_bank_qr ?? '')));

        switch ($choice) {
            case 'none':
                return null;
            case 'paybysquare':
                return $this->payBySquare->canGenerate($company, $document) ? 'paybysquare' : null;
            case 'epc':
                return $this->epc->canGenerate($company, $document) ? 'epc' : null;
            case 'swiss':
                return $this->swiss->canGenerate($company, $document) ? 'swiss' : null;
        }

        $payer = $this->payerCountry($company, $document);

        if (in_array($payer, ['SK', 'CZ'], true)) {
            return $this->payBySquare->canGenerate($company, $document) ? 'paybysquare' : null;
        }

        if (in_array($payer, ['CH', 'LI'], true)) {
            // Swiss QR only pays onto CH/LI accounts; EPC is the EUR fallback
            // (a subset of Swiss apps reads it - better than nothing).
            if ($this->swiss->canGenerate($company, $document)) {
                return 'swiss';
            }

            return $this->epc->canGenerate($company, $document) ? 'epc' : null;
        }

        if (in_array($payer, self::EPC_COUNTRIES, true)) {
            return $this->epc->canGenerate($company, $document) ? 'epc' : null;
        }

        return null;
    }

    /** Buyer's billing country, falling back to the issuer (domestic sale). */
    protected function payerCountry(Company $company, BusinessDocument $document): string
    {
        $contact = $document->resolvedBuyer();
        $country = $contact ? strtoupper(trim((string) $contact->country)) : '';
        if (strlen($country) === 2) {
            return $country;
        }

        $country = strtoupper(trim((string) $company->country));
        if (strlen($country) === 2) {
            return $country;
        }

        return match (JurisdictionRules::normalizeValue($company->jurisdiction)) {
            CompanyJurisdiction::EuSk->value => 'SK',
            CompanyJurisdiction::EuCz->value => 'CZ',
            CompanyJurisdiction::EuDe->value => 'DE',
            CompanyJurisdiction::EuAt->value => 'AT',
            CompanyJurisdiction::Ch->value => 'CH',
            default => '',
        };
    }
}
