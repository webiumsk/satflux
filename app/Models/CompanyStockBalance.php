<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyStockBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_warehouse_id',
        'company_stock_item_id',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(CompanyWarehouse::class, 'company_warehouse_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(CompanyStockItem::class, 'company_stock_item_id');
    }
}
