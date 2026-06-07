<?php

namespace App\Support\Invoicing;

use App\Support\Invoicing\Canonical\CanonicalInvoiceLine;
use App\Support\Invoicing\Canonical\CanonicalTaxBreakdownRow;

final class UsSalesTaxCalculationResult
{
    /**
     * @param  list<CanonicalInvoiceLine>  $lines
     * @param  list<CanonicalTaxBreakdownRow>  $taxBreakdown
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $taxBreakdown,
        public readonly string $provider,
        public readonly ?string $externalId = null,
    ) {}
}
