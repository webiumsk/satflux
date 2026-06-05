<?php

namespace Tests\Unit;

use App\Services\Invoicing\RecurringPlaceholderResolver;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlaceholderLegacyAliasesTest extends TestCase
{
    #[Test]
    public function legacy_recurring_placeholders_still_resolve(): void
    {
        $resolver = new RecurringPlaceholderResolver;
        $date = Carbon::parse('2026-03-15');

        $resolved = $resolver->resolve(
            'Faktúra #CISLOFAKTURY# / VS #VAR# / #ROK#',
            $date,
            '20260042',
            '20260042'
        );

        $this->assertSame('Faktúra 20260042 / VS 20260042 / 2026', $resolved);
    }
}
