<?php

namespace Tests\Unit;

use App\Enums\BankTransactionDirection;
use App\Models\BankTransaction;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankTransactionDirectionGuesserTest extends TestCase
{
    #[Test]
    public function infers_debit_from_stored_reference_when_db_direction_is_wrong(): void
    {
        $transaction = new BankTransaction([
            'amount' => 866.05,
            'direction' => BankTransactionDirection::Credit,
            'reference' => 'Debet na ucte (ID=090626/987089-3)',
        ]);

        $direction = app(BankTransactionDirectionGuesser::class)->inferFromTransaction($transaction);

        $this->assertSame(BankTransactionDirection::Debit, $direction);
    }

    #[Test]
    public function infers_debit_from_counterparty_pos_purchase_text(): void
    {
        $transaction = new BankTransaction([
            'amount' => 18.40,
            'direction' => BankTransactionDirection::Credit,
            'counterparty_name' => 'EUR NAKUP POS',
        ]);

        $direction = app(BankTransactionDirectionGuesser::class)->inferFromTransaction($transaction);

        $this->assertSame(BankTransactionDirection::Debit, $direction);
    }

    #[Test]
    public function wise_direction_in_is_credit_and_out_is_debit(): void
    {
        $guesser = app(BankTransactionDirectionGuesser::class);

        $this->assertSame(
            BankTransactionDirection::Credit,
            $guesser->fromAmountAndHints(100.0, 'IN'),
        );
        $this->assertSame(
            BankTransactionDirection::Debit,
            $guesser->fromAmountAndHints(100.0, 'OUT'),
        );
    }

    #[Test]
    public function generic_payment_text_is_not_directional_evidence(): void
    {
        $guesser = app(BankTransactionDirectionGuesser::class);

        $this->assertNull($guesser->fromHints('Platba 0900/000000-1234567890', 'Synthetic supplier invoice'));
        $this->assertSame(BankTransactionDirection::Debit, $guesser->fromAmountAndHints(-42.50, 'Synthetic supplier invoice'));
        $this->assertSame(BankTransactionDirection::Credit, $guesser->fromAmountAndHints(42.50, 'Synthetic customer invoice'));
    }
}
