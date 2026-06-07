<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use PHPUnit\Framework\TestCase;

class CompanyJurisdictionTest extends TestCase
{
    public function test_from_country_code_maps_uk_offshore_and_asia(): void
    {
        $this->assertSame(CompanyJurisdiction::Uk, CompanyJurisdiction::fromCountryCode('GB'));
        $this->assertSame(CompanyJurisdiction::Offshore, CompanyJurisdiction::fromCountryCode('KY'));
        $this->assertSame(CompanyJurisdiction::Asia, CompanyJurisdiction::fromCountryCode('HK'));
        $this->assertSame(CompanyJurisdiction::EuOther, CompanyJurisdiction::fromCountryCode('CH'));
    }

    public function test_pay_by_square_only_for_eu_jurisdictions(): void
    {
        $this->assertTrue(CompanyJurisdiction::EuSk->supportsPayBySquare());
        $this->assertFalse(CompanyJurisdiction::Uk->supportsPayBySquare());
        $this->assertFalse(CompanyJurisdiction::Offshore->supportsPayBySquare());
        $this->assertFalse(CompanyJurisdiction::Asia->supportsPayBySquare());
    }
}
