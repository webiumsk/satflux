<?php

namespace App\Support\Invoicing;

use App\Enums\BankTransactionDirection;
use Illuminate\Database\Eloquent\Builder;

final class BankTransactionListSummary
{
    /**
     * @return array{
     *     credit_count: int,
     *     credit_total: string|null,
     *     debit_count: int,
     *     debit_total: string|null,
     *     balance: string|null,
     *     currency: string|null,
     *     by_currency: list<array{
     *         currency: string,
     *         credit_count: int,
     *         credit_total: string,
     *         debit_count: int,
     *         debit_total: string,
     *         balance: string
     *     }>
     * }
     */
    public function forQuery(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('direction, currency, COUNT(*) as cnt, SUM(ABS(amount)) as total')
            ->groupBy('direction', 'currency')
            ->get();

        $creditCount = 0;
        $creditTotal = 0.0;
        $debitCount = 0;
        $debitTotal = 0.0;
        /** @var array<string, array{currency: string, credit_count: int, credit_total: float, debit_count: int, debit_total: float}> $byCurrency */
        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = $row->currency !== '' ? (string) $row->currency : 'EUR';
            $cnt = (int) $row->cnt;
            $total = (float) $row->total;
            $direction = $row->direction instanceof BankTransactionDirection
                ? $row->direction->value
                : (string) $row->direction;

            if (! isset($byCurrency[$currency])) {
                $byCurrency[$currency] = [
                    'currency' => $currency,
                    'credit_count' => 0,
                    'credit_total' => 0.0,
                    'debit_count' => 0,
                    'debit_total' => 0.0,
                ];
            }

            if ($direction === BankTransactionDirection::Credit->value) {
                $creditCount += $cnt;
                $creditTotal += $total;
                $byCurrency[$currency]['credit_count'] += $cnt;
                $byCurrency[$currency]['credit_total'] += $total;
            } else {
                $debitCount += $cnt;
                $debitTotal += $total;
                $byCurrency[$currency]['debit_count'] += $cnt;
                $byCurrency[$currency]['debit_total'] += $total;
            }
        }

        $singleCurrency = count($byCurrency) === 1 ? array_key_first($byCurrency) : null;

        $byCurrencyFormatted = array_map(fn (array $entry) => [
            'currency' => $entry['currency'],
            'credit_count' => $entry['credit_count'],
            'credit_total' => $this->formatMoney($entry['credit_total']),
            'debit_count' => $entry['debit_count'],
            'debit_total' => $this->formatMoney($entry['debit_total']),
            'balance' => $this->formatMoney($entry['credit_total'] - $entry['debit_total']),
        ], array_values($byCurrency));

        return [
            'credit_count' => $creditCount,
            'credit_total' => $singleCurrency !== null ? $this->formatMoney($creditTotal) : null,
            'debit_count' => $debitCount,
            'debit_total' => $singleCurrency !== null ? $this->formatMoney($debitTotal) : null,
            'balance' => $singleCurrency !== null ? $this->formatMoney($creditTotal - $debitTotal) : null,
            'currency' => $singleCurrency,
            'by_currency' => $byCurrencyFormatted,
        ];
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
