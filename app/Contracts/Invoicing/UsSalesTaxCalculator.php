<?php

namespace App\Contracts\Invoicing;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\Canonical\CanonicalInvoiceLine;
use App\Support\Invoicing\UsSalesTaxCalculationResult;

interface UsSalesTaxCalculator
{
    public function provider(): string;

    public function supports(Company $company): bool;

    /**
     * @param  list<CanonicalInvoiceLine>  $lines  Lines with net amounts; tax may be zero.
     */
    public function calculate(
        Company $company,
        ?CompanyContact $contact,
        array $lines,
        string $currency,
    ): UsSalesTaxCalculationResult;
}
