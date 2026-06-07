<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsSalesTaxManualTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function us_company_applies_manual_sales_tax_on_lines(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'US LLC',
            'jurisdiction' => CompanyJurisdiction::Us,
            'default_currency' => 'USD',
            'vat_payer' => false,
            'vat_rate_default' => 8.25,
            'app_settings' => ['us_sales_tax_provider' => 'manual'],
        ]);

        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($company, [
            ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 8.25],
        ]);

        $this->assertSame('100.00', $canonical->subtotal);
        $this->assertSame('8.25', $canonical->taxTotal);
        $this->assertSame('108.25', $canonical->total);
        $this->assertCount(1, $canonical->taxBreakdown);
        $this->assertSame('Sales tax 8.25%', $canonical->taxBreakdown[0]->label);
    }
}
