<?php

namespace App\Support\Invoicing\Canonical;

/**
 * Computed invoice line (single source of truth for net / tax / gross).
 */
final class CanonicalInvoiceLine
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $quantity,
        public readonly ?string $unit,
        public readonly float $unitPrice,
        public readonly float $lineDiscountPercent,
        public readonly float $taxRate,
        public readonly string $netAmount,
        public readonly string $taxAmount,
        public readonly string $grossAmount,
        public readonly int $sortOrder = 0,
    ) {}
}
