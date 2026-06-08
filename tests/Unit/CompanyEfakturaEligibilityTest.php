<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyEfakturaEligibilityTest extends TestCase
{
    #[Test]
    public function test_supports_full_vat_payer_sk_company(): void
    {
        $company = new Company([
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'vat_status' => 'payer',
            'vat_payer' => true,
        ]);

        $this->assertTrue(app(CompanyEfakturaEligibility::class)->supportsCompany($company));
    }

    #[Test]
    public function test_rejects_partial_and_non_payers(): void
    {
        $eligibility = app(CompanyEfakturaEligibility::class);

        $partial = new Company([
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'vat_status' => 'partial',
            'vat_payer' => true,
        ]);
        $none = new Company([
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'vat_status' => 'none',
            'vat_payer' => false,
        ]);

        $this->assertFalse($eligibility->supportsCompany($partial));
        $this->assertFalse($eligibility->supportsCompany($none));
    }
}
