<?php

namespace App\Models;

use App\Enums\BankMatchType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransactionMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_transaction_id',
        'business_document_id',
        'matched_amount',
        'match_type',
        'matched_by_user_id',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_amount' => 'decimal:2',
            'match_type' => BankMatchType::class,
            'matched_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }
}
