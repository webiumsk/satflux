<?php

namespace App\Support\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\BusinessExpense;
use Illuminate\Database\Eloquent\Builder;

final class BusinessExpenseListSummary
{
    /**
     * @param  Builder<BusinessExpense>  $query
     * @return array{count: int, currencies: list<array{currency: string, count: int, total: string, unpaid_total: string}>}
     */
    public static function fromQuery(Builder $query): array
    {
        $count = 0;
        $currencies = [];

        // Stream the filtered company result before pagination. Model decimal
        // strings and BCMath avoid a floating-point database/accumulator sum.
        foreach ((clone $query)->select(['status', 'total', 'currency'])->reorder()->cursor() as $expense) {
            $total = $expense->getAttribute('total');
            if (! is_string($total) || ! is_numeric($total) || ! preg_match('/^-?\d+\.\d{2}$/D', $total)) {
                throw new \UnexpectedValueException('Expense total is not a decimal amount.');
            }
            $currency = strtoupper(trim((string) $expense->currency)) ?: 'EUR';
            $currencies[$currency] ??= [
                'currency' => $currency,
                'count' => 0,
                'total' => '0.00',
                'unpaid_total' => '0.00',
            ];
            $count++;
            $currencies[$currency]['count']++;
            $currencies[$currency]['total'] = bcadd($currencies[$currency]['total'], $total, 2);
            if ($expense->getAttribute('status') === BusinessExpenseStatus::Recorded) {
                $currencies[$currency]['unpaid_total'] = bcadd($currencies[$currency]['unpaid_total'], $total, 2);
            }
        }

        ksort($currencies, SORT_STRING);

        return ['count' => $count, 'currencies' => array_values($currencies)];
    }
}
