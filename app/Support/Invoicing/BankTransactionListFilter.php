<?php

namespace App\Support\Invoicing;

use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\BusinessExpense;
use App\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Read-only list projection. Display direction never becomes settlement evidence. */
final class BankTransactionListFilter
{
    private const MAX_SAFE_MINOR = 9007199254740991;

    /** @return array<string, mixed> */
    public function validate(Request $request): array
    {
        $amount = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || self::minorUnits($value) === null) {
                $fail('The '.$attribute.' must be a nonnegative amount with at most two decimal places.');
            }
        };
        $text = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || str_contains($value, "\0") || preg_match('//u', $value) !== 1) {
                $fail('The '.$attribute.' must be valid UTF-8 text without null bytes.');
            }
        };
        $filters = $request->validate([
            'match_status' => ['sometimes', 'string', Rule::in(['unmatched', 'matched', 'ignored'])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'direction' => ['sometimes', 'string', Rule::in(['credit', 'debit'])],
            'amount_min' => ['sometimes', 'nullable', 'string', 'max:64', $amount],
            'amount_max' => ['sometimes', 'nullable', 'string', 'max:64', $amount],
            'search' => ['sometimes', 'nullable', 'string', 'max:200', $text],
            'variable_symbol' => ['sometimes', 'string', 'max:32', $text],
            'source' => ['sometimes', 'string', Rule::in(['csv', 'camt053', 'manual', 'email', 'wise'])],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ]);
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => ['The end date must not precede the start date.']]);
        }
        if (isset($filters['amount_min'], $filters['amount_max'])
            && self::minorUnits($filters['amount_min']) > self::minorUnits($filters['amount_max'])) {
            throw ValidationException::withMessages(['amount_max' => ['The maximum amount must not be below the minimum amount.']]);
        }

        return $filters;
    }

    /**
     * Scan bounded batches in stable display order. Only the selected page is retained.
     * Unicode literal search and legacy direction resolution happen before pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: Collection<int, BankTransaction>, meta: array<string, mixed>}
     */
    public function page(Company $company, array $filters): array
    {
        $query = BankTransaction::query()->where('company_id', $company->id);
        if (! empty($filters['match_status'])) {
            $query->where('match_status', $filters['match_status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('booked_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('booked_at', '<=', $filters['to']);
        }
        if (isset($filters['variable_symbol']) && $filters['variable_symbol'] !== '') {
            $query->whereRaw("variable_symbol LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($filters['variable_symbol']).'%']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $term = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $relations = [
            'match.document' => fn ($related) => $related->select(['id', 'company_id', 'number', 'title', 'status', 'total', 'currency', 'type'])->where('company_id', $company->id),
            'expense' => fn ($related) => $related->select(['id', 'company_id', 'internal_number', 'title', 'status', 'total', 'currency'])->where('company_id', $company->id),
        ];
        $scan = (clone $query)->excludingBalanceSnapshots()->orderByDesc('booked_at')->orderByDesc('id');
        if ($term !== '') {
            $scan->with($relations);
        }
        $minimum = isset($filters['amount_min']) ? self::minorUnits($filters['amount_min']) : null;
        $maximum = isset($filters['amount_max']) ? self::minorUnits($filters['amount_max']) : null;
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $rows = new Collection;
        /** @var array<string, array{currency: string, credit_count: int, debit_count: int, credit_total: int|float, debit_total: int|float}> $currencies */
        $currencies = [];

        foreach ($this->scanBatches($scan) as $transaction) {
            $direction = $transaction->resolvedDirection()->value;
            if (isset($filters['direction']) && $direction !== $filters['direction']) {
                continue;
            }
            // Stored records are not constrained by a UI filter-input ceiling.
            $storedMinor = self::storedMinorUnits((string) $transaction->amount);
            if (($minimum !== null || $maximum !== null) && ($storedMinor === null
                || ($minimum !== null && self::compareMinor($storedMinor, (string) $minimum) < 0)
                || ($maximum !== null && self::compareMinor($storedMinor, (string) $maximum) > 0))) {
                continue;
            }
            if ($term !== '' && ! $this->matchesSearch($transaction, $term)) {
                continue;
            }
            if ($total >= $offset && $rows->count() < $perPage) {
                $rows->push($transaction);
            }
            $total++;
            $currency = filled($transaction->currency) ? (string) $transaction->currency : 'EUR';
            $currencies[$currency] ??= ['currency' => $currency, 'credit_count' => 0, 'debit_count' => 0, 'credit_total' => 0, 'debit_total' => 0];
            $currencies[$currency][$direction.'_count']++;
            $currencies[$currency][$direction.'_total'] += abs((float) $transaction->amount);
        }

        $rows->load($relations);
        $byCurrency = array_map(fn (array $entry) => [
            'currency' => $entry['currency'],
            'credit_count' => $entry['credit_count'],
            'credit_total' => $this->money($entry['credit_total']),
            'debit_count' => $entry['debit_count'],
            'debit_total' => $this->money($entry['debit_total']),
            'balance' => $this->money($entry['credit_total'] - $entry['debit_total']),
        ], array_values($currencies));
        $single = count($byCurrency) === 1 ? $byCurrency[0] : null;
        // A dated bank-reported snapshot is independent of the filtered movement total.
        $snapshot = BankTransaction::query()->where('company_id', $company->id)->balanceSnapshotsOnly()
            ->orderByDesc('booked_at')->orderByDesc('id')->first();
        $snapshotDate = $snapshot?->getAttribute('booked_at');
        $summary = [
            'credit_count' => array_sum(array_column($byCurrency, 'credit_count')),
            'credit_total' => $single['credit_total'] ?? null,
            'debit_count' => array_sum(array_column($byCurrency, 'debit_count')),
            'debit_total' => $single['debit_total'] ?? null,
            'balance' => $single['balance'] ?? null,
            'currency' => $single['currency'] ?? null,
            'by_currency' => $byCurrency,
            'account_balance' => $snapshot ? [
                'amount' => number_format((float) $snapshot->amount, 2, '.', ''),
                'currency' => filled($snapshot->currency) ? (string) $snapshot->currency : 'EUR',
                'as_of' => $snapshotDate instanceof CarbonInterface ? $snapshotDate->toIso8601String() : '',
            ] : null,
        ];

        return ['rows' => $rows, 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage, 'total' => $total, 'summary' => $summary]];
    }

    /**
     * A composite cursor avoids offset rescans while preserving timestamp ties.
     *
     * @param  Builder<BankTransaction>  $scan
     * @return \Generator<int, BankTransaction>
     */
    private function scanBatches(Builder $scan): \Generator
    {
        $cursorDate = null;
        $cursorId = null;
        do {
            $batchQuery = clone $scan;
            if ($cursorId !== null) {
                $batchQuery->where(function (Builder $query) use ($cursorDate, $cursorId): void {
                    $query->where('booked_at', '<', $cursorDate)
                        ->orWhere(function (Builder $tie) use ($cursorDate, $cursorId): void {
                            $tie->where('booked_at', $cursorDate)->where('id', '<', $cursorId);
                        });
                });
            }
            $batch = $batchQuery->limit(250)->get();
            foreach ($batch as $transaction) {
                yield $transaction;
            }
            $last = $batch->last();
            $cursorDate = $last?->getRawOriginal('booked_at');
            $cursorId = $last?->id;
        } while ($batch->count() === 250);
    }

    private function matchesSearch(BankTransaction $transaction, string $term): bool
    {
        $match = $transaction->match;
        $document = $match instanceof BankTransactionMatch ? $match->document : null;
        $expense = $transaction->expense;
        foreach ([$transaction->variable_symbol, $transaction->counterparty_name, $transaction->counterparty_iban, $transaction->reference,
            $document instanceof BusinessDocument ? $document->number : null,
            $document instanceof BusinessDocument ? $document->title : null,
            $expense instanceof BusinessExpense ? $expense->internal_number : null,
            $expense instanceof BusinessExpense ? $expense->title : null] as $value) {
            if ($value !== null && str_contains(mb_strtolower((string) $value), $term)) {
                return true;
            }
        }

        return false;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function money(int|float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private static function storedMinorUnits(string $value): ?string
    {
        if (! preg_match('/^-?([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            return null;
        }

        return ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0') ?: '0';
    }

    private static function compareMinor(string $left, string $right): int
    {
        return (strlen($left) <=> strlen($right)) ?: strcmp($left, $right);
    }

    private static function minorUnits(string $value): ?int
    {
        if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            return null;
        }
        $whole = ltrim($matches[1], '0');
        if (strlen($whole) > 14) {
            return null;
        }
        $minor = ((int) $whole * 100) + (int) str_pad($matches[2] ?? '', 2, '0');

        return $minor <= self::MAX_SAFE_MINOR ? $minor : null;
    }
}
