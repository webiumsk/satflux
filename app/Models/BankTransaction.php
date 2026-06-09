<?php

namespace App\Models;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use HasUuids;

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
        return app(BankTransactionDirectionGuesser::class)->inferFromTransaction($this);
    }
}
