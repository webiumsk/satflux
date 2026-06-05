<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpenseAttachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_expense_id',
        'disk',
        'path',
        'original_filename',
        'mime',
        'size_bytes',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(BusinessExpense::class, 'business_expense_id');
    }
}
