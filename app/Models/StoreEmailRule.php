<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreEmailRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'store_id',
        'trigger',
        'condition',
        'to_addresses',
        'cc_addresses',
        'bcc_addresses',
        'send_to_buyer',
        'subject',
        'body',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'send_to_buyer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(StoreEmailRuleDispatch::class);
    }
}
