<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array{amount: string|null, currency: string|null, number: string|null} $terms
 */
class BusinessDocumentPaymentAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['terms'];

    protected function casts(): array
    {
        return ['terms' => 'encrypted:array'];
    }
}
