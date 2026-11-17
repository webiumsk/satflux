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

    #[Test]
    public function generic_payment_text_preserves_recorded_debit_with_positive_stored_amount(): void
    {
        $transaction = new BankTransaction([
            'amount' => 42.50,
            'direction' => BankTransactionDirection::Debit,
            'counterparty_name' => 'Platba 0900/000000-1234567890',
            'reference' => 'Synthetic supplier invoice 20260042',
        ]);

        $this->assertSame(BankTransactionDirection::Debit, $transaction->resolvedDirection());
        $this->assertFalse($transaction->isCredit());
        $this->assertSame(BankTransactionDirection::Debit, $transaction->direction);
        $this->assertSame('42.50', $transaction->amount);
    }

    #[Test]
    public function generic_payment_text_preserves_recorded_credit(): void
    {
        $transaction = new BankTransaction([
            'amount' => 42.50,
            'direction' => BankTransactionDirection::Credit,
            'counterparty_name' => 'Platba 0900/000000-1234567890',
            'reference' => 'Synthetic customer invoice 20260042',
        ]);

        $this->assertSame(BankTransactionDirection::Credit, $transaction->resolvedDirection());
        $this->assertTrue($transaction->isCredit());
    }

    #[Test]
    public function missing_recorded_direction_still_falls_back_to_signed_amount(): void
    {
        $transaction = new BankTransaction([
            'amount' => -42.50,
            'counterparty_name' => 'Synthetic supplier',
        ]);

        $this->assertSame(BankTransactionDirection::Debit, $transaction->resolvedDirection());
    }
}
