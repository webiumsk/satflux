<?php

namespace Tests\Unit;

use App\Services\Invoicing\DocumentNumberFormatter;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentNumberFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_year_and_counter_with_preferred_tokens(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2026-06-03');

        $this->assertSame('20260066', $formatter->format('YYYYNNNN', 66, $date));
        $this->assertSame('INV20260066', $formatter->format('INVYYYYNNNN', 66, $date));
    }

    #[Test]
    public function it_formats_year_and_counter_with_legacy_tokens(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2026-06-03');

        $this->assertSame('20260066', $formatter->format('RRRRCCCC', 66, $date));
    }

    #[Test]
    public function it_formats_literal_prefix_with_short_year(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2026-06-03');

        $this->assertSame('DOD26001', $formatter->format('DODRRCCC', 1, $date));
    }

    #[Test]
    public function it_formats_year_month_and_counter(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2026-06-03');

        $this->assertSame('OBJ202606001', $formatter->format('OBJRRRRMMCCC', 1, $date));
        $this->assertSame('OBJ202606001', $formatter->format('OBJYYYYMMNNN', 1, $date));
    }

    #[Test]
    public function it_treats_single_n_as_literal_prefix(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2026-06-03');

        $this->assertSame('N20260001', $formatter->format('NYYYYNNNN', 1, $date));
    }

    #[Test]
    public function it_requires_counter_token_in_format(): void
    {
        $formatter = new DocumentNumberFormatter;

        $this->expectException(InvalidArgumentException::class);
        $formatter->validateFormat('INVYYYY');
    }
}
