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
        'quantity_on_hand',
        'purchase_unit_price',
        'purchase_currency',
        'sale_unit_price',
        'internal_note',
        'exclude_from_suggester',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'quantity_on_hand' => 'decimal:4',
            'purchase_unit_price' => 'decimal:2',
            'sale_unit_price' => 'decimal:2',
            'exclude_from_suggester' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
