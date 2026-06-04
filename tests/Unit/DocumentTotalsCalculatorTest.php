<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\DocumentTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_vat_company_has_zero_tax(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Test s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'vat_payer' => false,
            'default_currency' => 'EUR',
        ]);

        $calc = app(DocumentTotalsCalculator::class);
        $totals = $calc->calculate($company, [
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 20],
        ]);

        $this->assertSame('100.00', $totals['subtotal']);
        $this->assertSame('0.00', $totals['tax_total']);
        $this->assertSame('100.00', $totals['total']);
    }

    #[Test]
    public function us_company_calculates_sales_tax_without_vat_payer_flag(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'US Co',
            'jurisdiction' => CompanyJurisdiction::Us,
            'vat_payer' => false,
            'default_currency' => 'USD',
            'app_settings' => ['us_sales_tax_provider' => 'manual'],
        ]);

        $totals = app(DocumentTotalsCalculator::class)->calculate($company, [
            ['quantity' => 1, 'unit_price' => 80, 'tax_rate' => 7.5],
        ]);

        $this->assertSame('80.00', $totals['subtotal']);
        $this->assertSame('6.00', $totals['tax_total']);
        $this->assertSame('86.00', $totals['total']);
    }
}
