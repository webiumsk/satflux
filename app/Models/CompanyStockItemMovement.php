<?php

namespace App\Models;

use App\Enums\CompanyStockMovementSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyStockItemMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'company_stock_item_id',
        'company_warehouse_id',
        'company_id',
        'quantity_after',
        'quantity_delta',
        'purchase_unit_price',
        'sale_unit_price',
        'note',
        'source',
        'business_document_id',
        'document_number',
        'document_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_after' => 'decimal:4',
            'quantity_delta' => 'decimal:4',
            'purchase_unit_price' => 'decimal:2',
            'sale_unit_price' => 'decimal:2',
            'source' => CompanyStockMovementSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(CompanyStockItem::class, 'company_stock_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(CompanyWarehouse::class, 'company_warehouse_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
