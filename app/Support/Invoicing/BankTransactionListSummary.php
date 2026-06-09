<?php

namespace App\Support\Invoicing;

use App\Enums\BankTransactionDirection;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Builder;

final class BankTransactionListSummary
{
    public function __construct(
        protected BankTransactionDirectionGuesser $guesser,
    ) {}

    /**
     * @return array{
     *     credit_count: int,
     *     credit_total: string,
     *     debit_count: int,
     *     debit_total: string,
     *     balance: string,
     *     currency: string
     * }
     */
    public function forQuery(Builder $query): array
    {
        $rows = (clone $query)->get([
            'amount',
            'currency',
            'direction',
            'reference',
            'counterparty_name',
        ]);

        $creditCount = 0;
        $creditTotal = 0.0;
        $debitCount = 0;
        $debitTotal = 0.0;
        $currency = 'EUR';

        foreach ($rows as $tx) {
            /** @var BankTransaction $tx */
            $amount = abs((float) $tx->amount);
            $dir = $this->guesser->inferFromTransaction($tx);
            if ($tx->currency !== '') {
                $currency = $tx->currency;
            }

            if ($dir === BankTransactionDirection::Credit) {
                $creditCount++;
                $creditTotal += $amount;
            } else {
                $debitCount++;
                $debitTotal += $amount;
            }
        }

        return [
            'credit_count' => $creditCount,
            'credit_total' => $this->formatMoney($creditTotal),
            'debit_count' => $debitCount,
            'debit_total' => $this->formatMoney($debitTotal),
            'balance' => $this->formatMoney($creditTotal - $debitTotal),
            'currency' => $currency,
        ];
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
