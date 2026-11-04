<?php

namespace App\Models;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use HasUuids;

    public const BANK_SYNC_MOVEMENT_FLAG = 'bank_sync_is_movement';

    private ?BankTransactionDirection $resolvedDirectionCache = null;

    protected $fillable = [
        'company_id',
        'bank_import_batch_id',
        'booked_at',
        'amount',
        'currency',
        'direction',
        'match_status',
        'business_expense_id',
        'variable_symbol',
        'constant_symbol',
        'specific_symbol',
        'counterparty_name',
        'counterparty_iban',
        'reference',
        'bank_transaction_id',
        'dedupe_hash',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'amount' => 'decimal:2',
            'direction' => BankTransactionDirection::class,
            'match_status' => BankTransactionMatchStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BankImportBatch::class, 'bank_import_batch_id');
    }

    public function match(): HasOne
    {
        return $this->hasOne(BankTransactionMatch::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(BusinessExpense::class, 'business_expense_id');
    }

    public function isCredit(): bool
    {
        return $this->resolvedDirection() === BankTransactionDirection::Credit;
    }

    public function resolvedDirection(): BankTransactionDirection
    {
        if ($this->resolvedDirectionCache !== null) {
            return $this->resolvedDirectionCache;
        }

        $guesser = app(BankTransactionDirectionGuesser::class);
        $inferred = $guesser->inferFromTransaction($this);

        if ($this->hasDirectionTextHints()) {
            $this->resolvedDirectionCache = $inferred;

            return $this->resolvedDirectionCache;
        }

        if ($this->direction instanceof BankTransactionDirection) {
            $this->resolvedDirectionCache = $this->direction;

            return $this->resolvedDirectionCache;
        }

        $this->resolvedDirectionCache = $inferred;

        return $this->resolvedDirectionCache;
    }

    protected function hasDirectionTextHints(): bool
    {
        return trim((string) ($this->reference ?? '')) !== ''
            || trim((string) ($this->counterparty_name ?? '')) !== '';
    }

    public function isAccountBalanceSnapshot(): bool
    {
        foreach ([$this->counterparty_name, $this->reference] as $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }

            $lower = mb_strtolower(trim($value));
            if (str_contains($lower, 'stav na ucte') || str_contains($lower, 'stav na účte')) {
                return true;
            }
        }

        return false;
    }

    /** Retain the existing driver-specific movement predicate for ordinary lists and summaries. */
    public function scopeExcludingBalanceSnapshots(Builder $query): Builder
    {
        $predicate = $this->bankMovementPredicate($query);

        return $query->whereRaw($predicate['sql'], $predicate['bindings']);
    }

    /** Classify raw scan candidates with the exact ordinary-list SQL predicate without excluding rows from the scan. */
    public function scopeWithBankSyncMovementFlag(Builder $query): Builder
    {
        $predicate = $this->bankMovementPredicate($query);
        if ($query->getQuery()->columns === null) {
            $query->select('*');
        }
        $alias = $query->getQuery()->getGrammar()->wrap(self::BANK_SYNC_MOVEMENT_FLAG);

        return $query->selectRaw('CASE WHEN '.$predicate['sql'].' THEN 1 ELSE 0 END AS '.$alias, $predicate['bindings']);
    }

    /**
     * Share NULL/LOWER/LIKE semantics between exclusion and projection without approximating database collation in PHP.
     *
     * @return array{sql: string, bindings: list<string>}
     */
    private function bankMovementPredicate(Builder $query): array
    {
        $clauses = [];
        $bindings = [];
        foreach (['counterparty_name', 'reference'] as $column) {
            $wrapped = $query->getQuery()->getGrammar()->wrap($column);
            $clauses[] = '('.$wrapped.' IS NULL OR (LOWER('.$wrapped.') NOT LIKE ? AND LOWER('.$wrapped.') NOT LIKE ?))';
            $bindings[] = '%stav na ucte%';
            $bindings[] = '%stav na účte%';
        }

        return ['sql' => '('.implode(' AND ', $clauses).')', 'bindings' => $bindings];
    }

    public function scopeBalanceSnapshotsOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (['counterparty_name', 'reference'] as $column) {
                $q->orWhere(function (Builder $inner) use ($column) {
                    $inner->whereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", ['%stav na ucte%'])
                        ->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", ['%stav na účte%']);
                });
            }
        });
    }
}
