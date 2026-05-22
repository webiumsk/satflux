<?php

namespace Tests\Unit;

use App\Support\RaffleBuyerPrivacy;
use PHPUnit\Framework\TestCase;

class RaffleBuyerPrivacyTest extends TestCase
{
    public function test_masks_email_local_part(): void
    {
        $this->assertSame('j***@example.com', RaffleBuyerPrivacy::maskEmail('john@example.com'));
    }

    public function test_masks_null_email_as_null(): void
    {
        $this->assertNull(RaffleBuyerPrivacy::maskEmail(null));
    }
}
