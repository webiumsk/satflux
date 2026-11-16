<?php

namespace App\Support\Invoicing;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class BusinessExpenseListFilters
{
    /** @var \WeakMap<\PDO, bool>|null */
    private static ?\WeakMap $registeredLowerFunctions = null;

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'overdue'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'issue_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'issue_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'due' => ['nullable', Rule::in(['all', 'overdue', 'custom'])],
            'due_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'amount_min' => ['nullable', 'regex:/^\d{1,20}(?:\.\d{1,2})?$/D'],
            'amount_max' => ['nullable', 'regex:/^\d{1,20}(?:\.\d{1,2})?$/D'],
            'search' => ['nullable', 'string', 'max:200', 'regex:/\\A[^\\x00]*\\z/u'],
        ];
    }

    /** @return array<string, mixed> */
    public static function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), self::rules());
        $validator->after(function ($validator) use ($request) {
            self::validateRanges($request, $validator);
        });

        return $validator->validate();
    }

    public static function validateRanges(Request $request, ValidatorContract $validator): void
    {
        if ($request->input('due') === 'custom' && ! $request->filled('due_from') && ! $request->filled('due_to')) {
            $validator->errors()->add('due_from', 'Select at least one due date bound.');
        }
        foreach ([['issue_from', 'issue_to'], ['due_from', 'due_to']] as [$from, $to]) {
            if (! $validator->errors()->hasAny([$from, $to]) && $request->filled($from) && $request->filled($to)
                && $request->input($from) > $request->input($to)) {
                $validator->errors()->add($to, 'The end date must be on or after the start date.');
            }
        }
        foreach (['amount_min', 'amount_max'] as $field) {
            if (! $validator->errors()->has($field) && $request->filled($field)
                && strcmp(self::amountKey((string) $request->input($field)), self::amountKey('90071992547409.91')) > 0) {
                $validator->errors()->add($field, 'The amount exceeds the supported exact-cent range.');
            }
        }
        if (! $validator->errors()->hasAny(['amount_min', 'amount_max'])
            && $request->filled('amount_min') && $request->filled('amount_max')
            && strcmp(self::amountKey((string) $request->input('amount_min')), self::amountKey((string) $request->input('amount_max'))) > 0) {
            $validator->errors()->add('amount_max', 'The maximum amount must be at least the minimum amount.');
        }
    }

    /** SQLite lacks Unicode LOWER; register only our fixed predicate function on this exact query connection. */
    public static function lowerFunction(ConnectionInterface $connection): string
    {
        if (! $connection instanceof Connection) {
            throw new \LogicException('Expense search requires a configured database connection.');
        }
        if ($connection->getDriverName() !== 'sqlite') {
            return 'LOWER';
        }
        $write = $connection->getPdo();
        $read = $connection->getReadPdo();
        $registered = self::$registeredLowerFunctions ??= new \WeakMap;
        foreach ([$write, $read] as $pdo) {
            if (isset($registered[$pdo])) {
                continue;
            }
            if (! $pdo->sqliteCreateFunction('satflux_expense_search_lower', static fn (?string $value): ?string => $value === null ? null : mb_strtolower($value), 1, \PDO::SQLITE_DETERMINISTIC)) {
                throw new \RuntimeException('Expense search collation is unavailable.');
            }
            $registered[$pdo] = true;
        }

        return 'satflux_expense_search_lower';
    }

    /** Fixed-width decimal strings compare exact cents without a floating-point conversion. */
    private static function amountKey(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return str_pad($whole, 20, '0', STR_PAD_LEFT).str_pad($fraction, 2, '0');
    }
}
