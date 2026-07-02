<?php

namespace Tests\Unit;

use App\Support\Invoicing\InvoiceFooterIcons;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceFooterIconsTest extends TestCase
{
    #[Test]
    public function it_returns_png_data_uris_for_footer_icons(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for invoice footer icons.');
        }

        foreach (['phone', 'web', 'email'] as $type) {
            $uri = InvoiceFooterIcons::dataUri($type);
            $this->assertStringStartsWith('data:image/png;base64,', $uri);
            $this->assertNotSame('', base64_decode(substr($uri, 22), true));
        }
    }
}
