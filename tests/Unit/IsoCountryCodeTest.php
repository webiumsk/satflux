<?php

namespace Tests\Unit;

use App\Support\Invoicing\IsoCountryCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IsoCountryCodeTest extends TestCase
{
    #[Test]
    public function it_normalizes_common_country_names_to_iso_codes(): void
    {
        $this->assertSame('SK', IsoCountryCode::normalize('Slovensko'));
        $this->assertSame('CZ', IsoCountryCode::normalize('Česko'));
        $this->assertSame('DE', IsoCountryCode::normalize('Nemecko'));
    }

    #[Test]
    public function it_keeps_two_letter_codes_uppercase(): void
    {
        $this->assertSame('SK', IsoCountryCode::normalize('sk'));
        $this->assertSame('CZ', IsoCountryCode::normalize('CZ'));
    }

    #[Test]
    public function it_returns_null_for_unknown_long_names(): void
    {
        $this->assertNull(IsoCountryCode::normalize('Unknownland'));
        $this->assertNull(IsoCountryCode::normalize(''));
        $this->assertNull(IsoCountryCode::normalize(null));
    }
}
