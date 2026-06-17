<?php

namespace Tests\Unit;

use App\Support\Invoicing\ImportDateParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportDateParserTest extends TestCase
{
    #[Test]
    public function parses_excel_short_slash_dates(): void
    {
        $parser = new ImportDateParser('auto');

        $this->assertSame('2026-01-04', $parser->parse('1/4/26')?->toDateString());
    }

    #[Test]
    public function parses_european_dot_dates_with_time_suffix(): void
    {
        $parser = new ImportDateParser('dmy_dot');

        $this->assertSame('2026-01-04', $parser->parse('04.01.2026 0:00:00')?->toDateString());
    }

    #[Test]
    public function ignores_invoice_numbers_as_excel_serials(): void
    {
        $parser = new ImportDateParser('auto');

        $this->assertNull($parser->parse('20260042'));
    }
}
