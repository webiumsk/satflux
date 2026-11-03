<?php

namespace App\Support\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\BusinessExpense;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

final class BusinessExpenseListSummary
{
    /**
     * Keep the summary, paginator count and page in the same database snapshot.
     * Transaction characteristics apply only to the transaction owned here.
     *
     * @template T
     *
     * @param  Builder<BusinessExpense>  $query
     * @param  Closure(): T  $read
     * @return T
     */
    public static function withinSnapshot(Builder $query, Closure $read): mixed
    {
        $connection = $query->getQuery()->getConnection();
        if (! $connection instanceof Connection) {
            throw new \LogicException('Expense listing snapshot requires a supported Laravel connection.');
        }
        $driver = $connection->getDriverName();
        $ownsTransaction = $connection->transactionLevel() === 0;

        if ($driver === 'pgsql' && ! $ownsTransaction) {
            $isolation = $connection->selectOne('SHOW transaction_isolation', [], false);
            if (! in_array($isolation?->transaction_isolation, ['repeatable read', 'serializable'], true)) {
                throw new \LogicException('Expense listing requires a stable outer transaction snapshot.');
            }
        } elseif ($driver !== 'pgsql' && $driver !== 'sqlite') {
            throw new \LogicException('Expense listing snapshot is unsupported for this database driver.');
        }

        return $connection->transaction(function () use ($connection, $driver, $ownsTransaction, $read) {
            if ($driver === 'pgsql' && $ownsTransaction) {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            } elseif ($driver === 'sqlite') {
                $settings = $connection->selectOne('PRAGMA read_uncommitted', [], false);
                if ((int) ($settings->read_uncommitted ?? 1) !== 0) {
                    throw new \LogicException('Expense listing requires SQLite committed-read isolation.');
                }
            }

            return $read();
        });
    }

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
