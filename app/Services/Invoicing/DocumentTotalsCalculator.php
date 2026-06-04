<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;

class DocumentTotalsCalculator
{
    public function __construct(
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    /**
     * @param  array<int, array{quantity: float|string, unit_price: float|string, line_discount_percent?: float|string, tax_rate?: float|string}>  $lines
     * @return array{subtotal: string, tax_total: string, total: string}
     */
    public function calculate(Company $company, array $lines, float $documentDiscountPercent = 0): array
    {
        $canonical = $this->canonicalBuilder->fromLinePayloads($company, $lines, $documentDiscountPercent);

        return [
            'subtotal' => $canonical->subtotal,
            'tax_total' => $canonical->taxTotal,
            'total' => $canonical->total,
        ];
    }

    public function applyToDocument(BusinessDocument $document, array $linePayloads, float $documentDiscountPercent = 0): void
    {
        $document->loadMissing('company');
        $canonical = $this->canonicalBuilder->fromLinePayloads(
            $document->company,
            $linePayloads,
            $documentDiscountPercent,
            $document,
            $document->contact,
        );

        $document->subtotal = $canonical->subtotal;
        $document->tax_total = $canonical->taxTotal;
        $document->total = $canonical->total;
        $document->discount_percent = $documentDiscountPercent;
    }
}
