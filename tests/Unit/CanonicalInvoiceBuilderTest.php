<?php

namespace Tests\Unit;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use App\Services\Invoicing\DocumentTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalInvoiceBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function vatCompany(array $overrides = []): Company
    {
        $user = User::factory()->create();

        return Company::create(array_merge([
            'user_id' => $user->id,
            'legal_name' => 'Test s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'vat_payer' => true,
            'vat_rate_default' => 23,
            'default_currency' => 'EUR',
        ], $overrides));
    }

    #[Test]
    public function per_line_rounding_rounds_each_line_before_totals(): void
    {
        $company = $this->vatCompany([
            'app_settings' => ['rounding_method' => 'per_line'],
        ]);

        $builder = app(CanonicalInvoiceBuilder::class);
        $canonical = $builder->fromLinePayloads($company, [
            ['name' => 'A', 'quantity' => 3, 'unit_price' => 10.333, 'tax_rate' => 23],
        ]);

        $this->assertSame('31.00', $canonical->lines[0]->netAmount);
        $this->assertSame('7.13', $canonical->lines[0]->taxAmount);
        $this->assertSame('38.13', $canonical->lines[0]->grossAmount);
        $this->assertSame('31.00', $canonical->subtotal);
        $this->assertSame('7.13', $canonical->taxTotal);
        $this->assertSame('38.13', $canonical->total);
    }

    #[Test]
    public function rounding_method_is_stored_on_canonical_invoice(): void
    {
        $lines = [['name' => 'A', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 23]];

        $perLine = app(CanonicalInvoiceBuilder::class)->fromLinePayloads(
            $this->vatCompany(['app_settings' => ['rounding_method' => 'per_line']]),
            $lines,
        );
        $perDocument = app(CanonicalInvoiceBuilder::class)->fromLinePayloads(
            $this->vatCompany(['app_settings' => ['rounding_method' => 'per_document']]),
            $lines,
        );

        $this->assertSame('per_line', $perLine->roundingMethod);
        $this->assertSame('per_document', $perDocument->roundingMethod);
    }

    #[Test]
    public function multi_rate_vat_produces_tax_breakdown_rows(): void
    {
        $company = $this->vatCompany();

        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($company, [
            ['name' => 'Standard', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23],
            ['name' => 'Reduced', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10],
        ]);

        $this->assertCount(2, $canonical->taxBreakdown);
        $rates = array_map(fn ($row) => $row->ratePercent, $canonical->taxBreakdown);
        $this->assertSame([10.0, 23.0], $rates);
        $this->assertSame('100.00', $canonical->taxBreakdown[0]->taxableAmount);
        $this->assertSame('10.00', $canonical->taxBreakdown[0]->taxAmount);
        $this->assertSame('100.00', $canonical->taxBreakdown[1]->taxableAmount);
        $this->assertSame('23.00', $canonical->taxBreakdown[1]->taxAmount);
        $this->assertSame('200.00', $canonical->subtotal);
        $this->assertSame('33.00', $canonical->taxTotal);
        $this->assertSame('233.00', $canonical->total);
    }

    #[Test]
    public function from_document_matches_persisted_totals_when_lines_are_consistent(): void
    {
        $company = $this->vatCompany();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260201',
            'subtotal' => '200.00',
            'tax_total' => '33.00',
            'total' => '233.00',
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Standard',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 23,
            'line_total' => '123.00',
        ]);
        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 1,
            'name' => 'Reduced',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 10,
            'line_total' => '110.00',
        ]);

        $canonical = app(CanonicalInvoiceBuilder::class)->fromDocument($doc->fresh(['company', 'contact', 'lines']));

        $this->assertSame('200.00', $canonical->subtotal);
        $this->assertSame('33.00', $canonical->taxTotal);
        $this->assertSame('233.00', $canonical->total);
    }

    #[Test]
    public function document_totals_calculator_delegates_to_canonical_builder(): void
    {
        $company = $this->vatCompany();

        $totals = app(DocumentTotalsCalculator::class)->calculate($company, [
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 23],
        ], 0);

        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($company, [
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 23],
        ]);

        $this->assertSame($canonical->subtotal, $totals['subtotal']);
        $this->assertSame($canonical->taxTotal, $totals['tax_total']);
        $this->assertSame($canonical->total, $totals['total']);
    }
}
