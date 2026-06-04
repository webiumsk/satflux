<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessRecurringProfileLine extends Model
{
    protected $fillable = [
        'business_recurring_profile_id',
        'sort_order',
        'name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'line_discount_percent',
        'tax_rate',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(BusinessRecurringProfile::class, 'business_recurring_profile_id');
    }
}
