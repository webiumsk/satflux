<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Support\Invoicing\ViesVatNumber;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViesVatNumberTest extends TestCase
{
    #[Test]
    public function parses_prefixed_vat_number(): void
    {
        $parsed = ViesVatNumber::parse('SK 2023980035');

        $this->assertSame(['country_code' => 'SK', 'vat_number' => '2023980035'], $parsed);
    }

    #[Test]
    public function uses_default_country_for_numeric_only_input(): void
    {
        $parsed = ViesVatNumber::parse('2023980035', 'SK');

        $this->assertSame('SK', $parsed['country_code']);
        $this->assertSame('2023980035', $parsed['vat_number']);
    }

    #[Test]
    public function default_country_from_jurisdiction(): void
    {
        $this->assertSame('SK', ViesVatNumber::defaultCountryFromJurisdiction(CompanyJurisdiction::EuSk));
        $this->assertSame('CZ', ViesVatNumber::defaultCountryFromJurisdiction(CompanyJurisdiction::EuCz));
        $this->assertSame('GB', ViesVatNumber::defaultCountryFromJurisdiction(CompanyJurisdiction::Uk));
    }
}
