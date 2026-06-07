<?php

namespace App\Services\Invoicing\UsSalesTax;

use App\Contracts\Invoicing\UsSalesTaxCalculator;
use App\Enums\CompanyJurisdiction;
use App\Enums\UsSalesTaxProvider;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\Canonical\CanonicalInvoiceLine;
use App\Support\Invoicing\Canonical\CanonicalTaxBreakdownRow;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\UsSalesTaxCalculationResult;
use Illuminate\Validation\ValidationException;

class UsSalesTaxCalculationService
{
    /**
     * @param  iterable<UsSalesTaxCalculator>  $calculators
     */
    public function __construct(
        protected iterable $calculators,
    ) {}

    public function isUsCompany(Company $company): bool
    {
        return $company->jurisdiction === CompanyJurisdiction::Us;
    }

    public function usesPartnerTax(Company $company): bool
    {
        if (! $this->isUsCompany($company)) {
            return false;
        }

        $provider = CompanyAppSettings::from($company->app_settings)->get('us_sales_tax_provider', 'manual');

        return in_array($provider, [
            UsSalesTaxProvider::StripeTax->value,
            UsSalesTaxProvider::Avalara->value,
        ], true);
    }

    /**
     * @param  list<CanonicalInvoiceLine>  $lines
     */
    public function applyIfNeeded(
        Company $company,
        ?CompanyContact $contact,
        array $lines,
        string $currency,
    ): UsSalesTaxCalculationResult {
        if (! $this->isUsCompany($company)) {
            return new UsSalesTaxCalculationResult($lines, [], 'none');
        }

        $settings = CompanyAppSettings::from($company->app_settings);
        $provider = (string) $settings->get('us_sales_tax_provider', UsSalesTaxProvider::Manual->value);

        if ($provider === UsSalesTaxProvider::Avalara->value) {
            throw ValidationException::withMessages([
                'us_sales_tax' => ['Avalara integration is not configured yet. Use manual rates or Stripe Tax.'],
            ]);
        }

        if ($provider === UsSalesTaxProvider::Manual->value) {
            return new UsSalesTaxCalculationResult(
                lines: $lines,
                taxBreakdown: $this->manualBreakdown($lines),
                provider: UsSalesTaxProvider::Manual->value,
            );
        }

        $calculator = $this->resolveCalculator($company);
        if ($calculator === null) {
            throw ValidationException::withMessages([
                'us_sales_tax' => ['No US sales tax calculator is configured for this provider.'],
            ]);
        }

        return $calculator->calculate($company, $contact, $lines, $currency);
    }

    /**
     * @param  list<CanonicalInvoiceLine>  $lines
     * @return list<CanonicalTaxBreakdownRow>
     */
    protected function manualBreakdown(array $lines): array
    {
        $buckets = [];
        foreach ($lines as $line) {
            $tax = (float) $line->taxAmount;
            if ($tax <= 0) {
                continue;
            }
            $key = number_format($line->taxRate, 2, '.', '');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['rate' => $line->taxRate, 'net' => 0.0, 'tax' => 0.0];
            }
            $buckets[$key]['net'] += (float) $line->netAmount;
            $buckets[$key]['tax'] += $tax;
        }

        ksort($buckets);
        $rows = [];
        foreach ($buckets as $bucket) {
            $net = round($bucket['net'], 2);
            $tax = round($bucket['tax'], 2);
            $rows[] = new CanonicalTaxBreakdownRow(
                ratePercent: $bucket['rate'],
                taxableAmount: number_format($net, 2, '.', ''),
                taxAmount: number_format($tax, 2, '.', ''),
                grossAmount: number_format($net + $tax, 2, '.', ''),
                label: 'Sales tax '.$bucket['rate'].'%',
            );
        }

        return $rows;
    }

    protected function resolveCalculator(Company $company): ?UsSalesTaxCalculator
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($company)) {
                return $calculator;
            }
        }

        return null;
    }
}
