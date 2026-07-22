<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;

/**
 * VAT display and calculation rules for EU companies, including §7 / §7a partial payers.
 *
 * Partial (§7a) payers distinguish the counterparty in three tiers: domestic
 * and non-EU supplies show no VAT at all; EU (non-domestic) supplies show a
 * 0% VAT summary plus the reverse-charge note.
 */
final class CompanyVatPolicy
{
    public const PARTIAL_REVERSE_CHARGE_NOTE = 'The supply of goods is exempt. The supply of services is subject to the reverse charge procedure.';

    /**
     * EU VAT area member codes - mirror of resources/js/config/euVatCountries.ts
     * ('EL' is the VIES alias for Greece).
     *
     * @var list<string>
     */
    public const EU_VAT_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT',
        'RO', 'SE', 'SI', 'SK',
    ];

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

    /**
     * Counterparty tier for VAT display: 'domestic' (same country or empty),
     * 'eu' (EU member other than the supplier's country) or 'non_eu'.
     * A country that does not normalize to a known ISO2 code falls to
     * 'non_eu' - it is never treated as an EU reverse-charge case.
     *
     * @return 'domestic'|'eu'|'non_eu'
     */
    public function supplyRegion(Company $company, ?CompanyContact $contact): string
    {
        if ($this->isDomesticSupply($company, $contact)) {
            return 'domestic';
        }

        $buyer = $this->normalizeCountryCode((string) $contact?->country);
        if (strlen($buyer) === 2 && in_array($buyer, self::EU_VAT_COUNTRIES, true)) {
            return 'eu';
        }

        return 'non_eu';
    }

    /**
     * §4 full payer invoicing a VAT-registered business in another EU state:
     * the tax liability transfers to the buyer (reverse charge / exemption),
     * so no VAT is charged and the invoice carries the note. Requires the
     * counterparty's IC DPH (vat_id) - B2C EU supplies keep normal VAT.
     */
    public function euB2bReverseCharge(Company $company, ?CompanyContact $contact): bool
    {
        return $this->isFullPayer($company)
            && $this->supplyRegion($company, $contact) === 'eu'
            && trim((string) $contact?->vat_id) !== '';
    }

    public function calculatesVatAmounts(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return true;
        }

        return $this->isFullPayer($company) && ! $this->euB2bReverseCharge($company, $contact);
    }

    public function showsVatRateColumn(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return true;
        }

        if ($this->isFullPayer($company)) {
            return true;
        }

        // §7a: the VAT rate column only appears on EU reverse-charge
        // invoices (rate 0); domestic and non-EU supplies show no VAT.
        return $this->isPartialPayer($company) && $this->supplyRegion($company, $contact) === 'eu';
    }

    public function showsVatBreakdown(Company $company, ?CompanyContact $contact = null): bool
    {
        if ($company->jurisdiction === CompanyJurisdiction::Us) {
            return false;
        }

        if ($this->isFullPayer($company)) {
            return true;
        }

        // §7a EU supply: show the summary with VAT 0 next to the
        // reverse-charge note.
        return $this->isPartialPayer($company) && $this->supplyRegion($company, $contact) === 'eu';
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
        $applies = ($this->isPartialPayer($company) && $this->supplyRegion($company, $contact) === 'eu')
            || $this->euB2bReverseCharge($company, $contact);

        if ($applies) {
            // A custom note from the company app settings wins over the
            // statutory default wording.
            $custom = $settings->bool('reverse_charge') ? trim((string) $settings->get('reverse_charge_note')) : '';

            return $custom !== '' ? $custom : __(self::PARTIAL_REVERSE_CHARGE_NOTE);
        }

        if ($settings->bool('reverse_charge') && $contact && trim((string) $contact->vat_id) !== '') {
            return (string) ($settings->get('reverse_charge_note')
                ?: __('Reverse charge - VAT to be accounted for by the recipient.'));
        }

        return null;
    }

    protected function normalizeCountryCode(string $country): string
    {
        $code = strtoupper(trim($country));

        // Canonicalize the Greek VIES alias so 'EL' vs 'GR' never
        // misclassifies a domestic supply as cross-border.
        return $code === 'EL' ? 'GR' : $code;
    }
}
