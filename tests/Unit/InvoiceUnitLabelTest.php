<?php

namespace Tests\Unit;

use App\Support\Invoicing\InvoiceUnitLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceUnitLabelTest extends TestCase
{
    #[Test]
    public function it_translates_preset_units_by_locale(): void
    {
        app()->setLocale('en');
        $this->assertSame('pcs', InvoiceUnitLabel::format('ks.'));
        $this->assertSame('hrs', InvoiceUnitLabel::format('hod.'));

        app()->setLocale('sk');
        $this->assertSame('ks.', InvoiceUnitLabel::format('ks.'));
        $this->assertSame('hod.', InvoiceUnitLabel::format('hod'));
    }

    #[Test]
    public function it_returns_custom_units_unchanged(): void
    {
        app()->setLocale('en');
        $this->assertSame('license', InvoiceUnitLabel::format('license'));
    }
}
