<?php

namespace Tests\Unit;

use App\Models\BankTransaction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankTransactionBalanceSnapshotTest extends TestCase
{
    #[Test]
    public function detects_account_balance_snapshot_from_counterparty(): void
    {
        $transaction = new BankTransaction([
            'counterparty_name' => 'Stav na účte',
            'reference' => 'Stav na ucte (ID=100626/103565-3)',
        ]);

        $this->assertTrue($transaction->isAccountBalanceSnapshot());
    }

    #[Test]
    public function regular_movement_is_not_balance_snapshot(): void
    {
        $transaction = new BankTransaction([
            'counterparty_name' => 'Platba 1100/000000-2629709868',
            'reference' => 'COD - DOBIERKA:0610182023',
        ]);

        $this->assertFalse($transaction->isAccountBalanceSnapshot());
    }
}
