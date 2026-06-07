<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;

/**
 * VAT display and calculation rules for EU companies, including §7 / §7a partial payers.
 */
final class CompanyVatPolicy
{
    public const PARTIAL_REVERSE_CHARGE_NOTE = 'The supply of goods is exempt. The supply of services is subject to the reverse charge procedure.';

    public function vatStatus(Company $company): string
    {
        $status = (string) ($company->vat_status ?? '');

        if ($status === 'payer' || $status === 'partial') {
            return $status;
        }

        if ($status === 'none' || $status === '') {
            return $company->vat_payer ? 'payer' : 'none';
        }

        return $company->vat_payer ? 'payer' : 'none';
    }

    public function isPartialPayer(Company $company): bool
    {
        return $this->vatStatus($company) === 'partial';
    }

    public function isFullPayer(Company $company): bool
    {
        return $this->vatStatus($company) === 'payer';
    }

    public function isDomesticSupply(Company $company, ?CompanyContact $contact): bool
    {
        if ($contact === null) {
            return true;
        }

        $seller = $this->normalizeCountryCode((string) $company->country);
        $buyer = $this->normalizeCountryCode((string) $contact->country);

        if ($seller === '' || $buyer === '') {
            return true;
        }

        return $seller === $buyer;
    }

    public function isForeignSupply(Company $company, ?CompanyContact $contact): bool
    {
        return ! $this->isDomesticSupply($company, $contact);
    }

    public function calculatesVatAmounts(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return true;
        }

        return $this->isFullPayer($company);
    }

    public function showsVatRateColumn(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return true;
        }

        if ($this->isFullPayer($company)) {
            return true;
        }

        return $this->isPartialPayer($company) && $this->isForeignSupply($company, $contact);
    }

    public function showsVatBreakdown(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return false;
        }

        return $this->isFullPayer($company);
    }

    public function defaultTaxRate(Company $company, ?CompanyContact $contact = null): float
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return (float) ($company->vat_rate_default ?? 0);
        }

        if ($this->isFullPayer($company)) {
            return (float) ($company->vat_rate_default ?? 0);
        }

        return 0.0;
    }

    public function resolveLineTaxRate(Company $company, ?CompanyContact $contact, ?float $requestedRate = null): float
    {
        if (! $this->calculatesVatAmounts($company, $contact)) {
            return 0.0;
        }

        return $requestedRate ?? $this->defaultTaxRate($company, $contact);
    }

    public function vatApplicableForIsdoc(Company $company, ?CompanyContact $contact = null): bool
    {
        return $this->isFullPayer($company);
    }

    public function reverseChargeNote(
        Company $company,
        ?CompanyContact $contact,
        CompanyAppSettings $settings,
    ): ?string {
        if ($this->isPartialPayer($company) && $this->isForeignSupply($company, $contact)) {
            return __(self::PARTIAL_REVERSE_CHARGE_NOTE);
        }

        if ($settings->bool('reverse_charge') && $contact && trim((string) $contact->vat_id) !== '') {
            return (string) ($settings->get('reverse_charge_note')
                ?: __('Reverse charge - VAT to be accounted for by the recipient.'));
        }

        return null;
    }

    protected function normalizeCountryCode(string $country): string
    {
        return strtoupper(trim($country));
    }
}
