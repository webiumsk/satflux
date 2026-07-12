<?php

namespace Tests\Unit;

use App\Support\LogSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogSanitizerTest extends TestCase
{
    #[Test]
    public function it_masks_the_local_part_of_an_email(): void
    {
        $this->assertSame('s***@example.com', LogSanitizer::email('samuel@example.com'));
        $this->assertSame('a***@sub.example.org', LogSanitizer::email('a.b+tag@sub.example.org'));
    }

    #[Test]
    public function it_fully_masks_non_email_input(): void
    {
        $this->assertSame('***', LogSanitizer::email('not-an-email'));
        $this->assertSame('***', LogSanitizer::email('@example.com'));
        $this->assertSame('', LogSanitizer::email(null));
        $this->assertSame('', LogSanitizer::email(''));
    }

    #[Test]
    public function it_masks_ibans_keeping_prefix_and_suffix(): void
    {
        $this->assertSame('SK31***7541', LogSanitizer::iban('SK3112000000198742637541'));
        $this->assertSame('SK31***7541', LogSanitizer::iban('SK31 1200 0000 1987 4263 7541'));
        $this->assertSame('***', LogSanitizer::iban('SK31'));
        $this->assertSame('', LogSanitizer::iban(null));
    }
}
