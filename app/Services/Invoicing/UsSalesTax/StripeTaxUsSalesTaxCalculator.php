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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StripeTaxUsSalesTaxCalculator implements UsSalesTaxCalculator
{
    public function provider(): string
    {
        return UsSalesTaxProvider::StripeTax->value;
    }

    public function supports(Company $company): bool
    {
        if ($company->jurisdiction !== CompanyJurisdiction::Us) {
            return false;
        }

        $settings = CompanyAppSettings::from($company->app_settings);

        return $settings->get('us_sales_tax_provider') === UsSalesTaxProvider::StripeTax->value
            && $this->secretKey($company) !== null;
    }

    public function calculate(
        Company $company,
        ?CompanyContact $contact,
        array $lines,
        string $currency,
    ): UsSalesTaxCalculationResult {
        $secret = $this->secretKey($company);
        if ($secret === null) {
            throw ValidationException::withMessages([
                'us_sales_tax' => ['Stripe Tax secret key is not configured for this company.'],
            ]);
        }

        $address = $this->customerAddress($company, $contact);

        $payload = [
            'currency' => strtolower($currency),
            'customer_details[address][country]' => 'US',
        ];

        foreach ($address as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload["customer_details[address][{$key}]"] = $value;
            }
        }

        foreach ($lines as $index => $line) {
            $ref = 'line-'.$index;
            $amountCents = (int) round((float) $line->netAmount * 100);
            $payload["line_items[{$index}][amount]"] = $amountCents;
            $payload["line_items[{$index}][reference]"] = $ref;
            $payload["line_items[{$index}][tax_code]"] = 'txcd_99999999';
        }

        try {
            $response = Http::withToken($secret)
                ->asForm()
                ->timeout(20)
                ->post('https://api.stripe.com/v1/tax/calculations', $payload);
        } catch (\Throwable $e) {
            Log::warning('Stripe Tax calculation failed', ['message' => $e->getMessage()]);
            throw ValidationException::withMessages([
                'us_sales_tax' => ['Stripe Tax service is temporarily unavailable.'],
            ]);
        }

        if (! $response->successful()) {
            Log::warning('Stripe Tax calculation HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw ValidationException::withMessages([
                'us_sales_tax' => ['Stripe Tax rejected the calculation request.'],
            ]);
        }

        return $this->mapResponse($lines, $response->json(), $currency);
    }

    /**
     * @param  list<CanonicalInvoiceLine>  $lines
     * @param  array<string, mixed>|null  $payload
     */
    protected function mapResponse(array $lines, ?array $payload, string $currency): UsSalesTaxCalculationResult
    {
        $lineItems = $payload['line_items']['data'] ?? $payload['line_items'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        $taxByReference = [];
        foreach ($lineItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $ref = (string) ($item['reference'] ?? '');
            $taxByReference[$ref] = ((int) ($item['amount_tax'] ?? 0)) / 100;
        }

        $updatedLines = [];
        foreach ($lines as $index => $line) {
            $ref = 'line-'.$index;
            $tax = $taxByReference[$ref] ?? 0.0;
            $net = (float) $line->netAmount;
            $rate = $net > 0 ? round(($tax / $net) * 100, 4) : 0.0;

            $updatedLines[] = new CanonicalInvoiceLine(
                name: $line->name,
                description: $line->description,
                quantity: $line->quantity,
                unit: $line->unit,
                unitPrice: $line->unitPrice,
                lineDiscountPercent: $line->lineDiscountPercent,
                taxRate: $rate,
                netAmount: $line->netAmount,
                taxAmount: $this->formatMoney($tax),
                grossAmount: $this->formatMoney($net + $tax),
                sortOrder: $line->sortOrder,
            );
        }

        $breakdown = [];
        $breakdownItems = $payload['tax_breakdown'] ?? [];
        if (is_array($breakdownItems)) {
            foreach ($breakdownItems as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $taxable = ((int) ($row['taxable_amount'] ?? 0)) / 100;
                $taxAmount = ((int) ($row['tax_amount'] ?? 0)) / 100;
                $details = $row['tax_rate_details'] ?? [];
                $rate = is_array($details)
                    ? (float) ($details['percentage_decimal'] ?? 0)
                    : 0.0;
                $label = $this->breakdownLabel(is_array($details) ? $details : []);

                $breakdown[] = new CanonicalTaxBreakdownRow(
                    ratePercent: $rate,
                    taxableAmount: $this->formatMoney($taxable),
                    taxAmount: $this->formatMoney($taxAmount),
                    grossAmount: $this->formatMoney($taxable + $taxAmount),
                    label: $label,
                );
            }
        }

        return new UsSalesTaxCalculationResult(
            lines: $updatedLines,
            taxBreakdown: $breakdown,
            provider: $this->provider(),
            externalId: isset($payload['id']) ? (string) $payload['id'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function breakdownLabel(array $details): string
    {
        $state = (string) ($details['state'] ?? '');
        $type = str_replace('_', ' ', (string) ($details['tax_type'] ?? 'sales tax'));

        return trim($state.' '.$type) ?: 'Sales tax';
    }

    /**
     * @return array{line1?: string, city?: string, state?: string, postal_code?: string}
     */
    protected function customerAddress(Company $company, ?CompanyContact $contact): array
    {
        $entity = $contact ?? $company;
        $state = $contact?->state_region ?? $company->state_region;
        $postal = $contact?->postal_code ?? $company->postal_code;

        if ($state === null || $state === '' || $postal === null || $postal === '') {
            throw ValidationException::withMessages([
                'contact' => ['US sales tax requires customer state and ZIP/postal code.'],
            ]);
        }

        return [
            'line1' => $entity->street ?: 'Unknown',
            'city' => $entity->city ?: 'Unknown',
            'state' => strtoupper(substr((string) $state, 0, 2)),
            'postal_code' => (string) $postal,
        ];
    }

    protected function secretKey(Company $company): ?string
    {
        $settings = CompanyAppSettings::from($company->app_settings);
        $fromCompany = trim((string) $settings->get('stripe_tax_secret_key', ''));
        if ($fromCompany !== '') {
            return $fromCompany;
        }

        $global = trim((string) config('services.stripe.tax_secret_key', ''));

        return $global !== '' ? $global : null;
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
