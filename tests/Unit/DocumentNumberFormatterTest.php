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

    #[Test]
    public function it_parses_numbers_with_the_full_format(): void
    {
        $formatter = new DocumentNumberFormatter;

        $this->assertSame(['counter' => 66, 'year' => '2026', 'month' => null], $formatter->parse('INVYYYYNNNN', 'INV20260066'));
        // Year after the counter, overflowing counter, foreign number.
        $this->assertSame(12, $formatter->parse('FNNNNYYYY', 'F00122026')['counter']);
        $this->assertSame(10000, $formatter->parse('INVYYYYNNNN', 'INV202610000')['counter']);
        $this->assertNull($formatter->parse('FVYYYYNNNNN', 'INV20260012'));
    }

    #[Test]
    public function it_counts_only_numbers_of_the_current_period(): void
    {
        $formatter = new DocumentNumberFormatter;
        $date = Carbon::parse('2027-01-02');

        $this->assertNull($formatter->counterInPeriod('INVYYYYNNNN', 'INV20260342', 'yearly', $date));
        $this->assertSame(3, $formatter->counterInPeriod('INVYYYYNNNN', 'INV20270003', 'yearly', $date));
        $this->assertSame(342, $formatter->counterInPeriod('INVYYYYNNNN', 'INV20260342', 'never', $date));
        // No year in the format: the issue date decides.
        $this->assertNull($formatter->counterInPeriod('FVNNNN', 'FV0342', 'yearly', $date, Carbon::parse('2026-12-30')));
        $this->assertSame(4, $formatter->counterInPeriod('FVNNNN', 'FV0004', 'yearly', $date, Carbon::parse('2027-01-02')));
        // Monthly reset.
        $this->assertNull($formatter->counterInPeriod('YYYYMMNNN', '202612005', 'monthly', $date));
        $this->assertSame(5, $formatter->counterInPeriod('YYYYMMNNN', '202701005', 'monthly', $date));
    }
}
