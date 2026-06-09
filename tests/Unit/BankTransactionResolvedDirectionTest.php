<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Models\BankTransaction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankTransactionResolvedDirectionTest extends TestCase
{
    #[Test]
    public function resolved_direction_prefers_text_hints_over_wrong_persisted_value(): void
    {
        $transaction = new BankTransaction([
            'amount' => 866.05,
            'direction' => BankTransactionDirection::Credit,
            'counterparty_name' => 'Debet na ucte (ID=090626/987089-3)',
        ]);

        $this->assertSame(BankTransactionDirection::Debit, $transaction->resolvedDirection());
    }

    #[Test]
    public function resolved_direction_uses_persisted_value_when_no_text_hints(): void
    {
        $transaction = new BankTransaction([
            'amount' => 150.00,
            'direction' => BankTransactionDirection::Debit,
        ]);

        $this->assertSame(BankTransactionDirection::Debit, $transaction->resolvedDirection());
    }
}
