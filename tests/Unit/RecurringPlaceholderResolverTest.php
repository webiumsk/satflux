<?php

namespace Tests\Unit;

use App\Services\Invoicing\RecurringPlaceholderResolver;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringPlaceholderResolverTest extends TestCase
{
    #[Test]
    public function variable_symbol_token_is_digits_only(): void
    {
        $resolver = new RecurringPlaceholderResolver;
        $issueDate = Carbon::parse('2026-06-15');

        $resolved = $resolver->resolve(
            '#VARIABLE_SYMBOL#',
            $issueDate,
            'INV20260042',
            '#VARIABLE_SYMBOL#',
        );

        $this->assertSame('20260042', $resolved);
    }

    #[Test]
    public function invoice_number_token_keeps_full_number(): void
    {
        $resolver = new RecurringPlaceholderResolver;
        $issueDate = Carbon::parse('2026-06-15');

        $resolved = $resolver->resolve(
            '#INVOICE_NUMBER#',
            $issueDate,
            'INV20260042',
            '#VARIABLE_SYMBOL#',
        );

        $this->assertSame('INV20260042', $resolved);
    }
}
