<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array{amount: string, currency: string, number: string|null} $terms
 * @property Carbon|null $paid_at
 * @property-read Store|null $store
 */
class BusinessDocumentPaymentIntent extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'terms', 'fingerprint', 'attempt_id', 'document_key'];

    protected function casts(): array
    {
        return ['terms' => 'encrypted:array', 'version' => 'integer', 'paid_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
