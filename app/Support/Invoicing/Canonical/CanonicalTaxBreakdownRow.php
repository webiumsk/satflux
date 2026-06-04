<?php

namespace App\Support\Invoicing\Canonical;

final class CanonicalTaxBreakdownRow
{
    public function __construct(
        public readonly float $ratePercent,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $grossAmount,
        public readonly ?string $label = null,
    ) {}
}
