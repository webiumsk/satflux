<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyStockItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'unit',
        'track_inventory',
        'purchase_unit_price',
        'purchase_currency',
        'sale_unit_price',
        'internal_note',
        'exclude_from_suggester',
    ];

    protected $appends = [
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'purchase_unit_price' => 'decimal:2',
            'sale_unit_price' => 'decimal:2',
            'exclude_from_suggester' => 'boolean',
        ];
    }

    public function getQuantityOnHandAttribute(): float
    {
        if ($this->relationLoaded('balances')) {
            return (float) $this->balances->sum(fn (CompanyStockBalance $balance) => (float) $balance->quantity_on_hand);
        }

        return (float) $this->balances()->sum('quantity_on_hand');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(CompanyStockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CompanyStockItemMovement::class)->orderByDesc('created_at');
    }

    public function documentLines(): HasMany
    {
        return $this->hasMany(BusinessDocumentLine::class);
    }
}
