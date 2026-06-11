<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocumentLine extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_document_id',
        'company_stock_item_id',
        'company_warehouse_id',
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
            'unit_price' => 'decimal:2',
            'line_discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(CompanyStockItem::class, 'company_stock_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(CompanyWarehouse::class, 'company_warehouse_id');
    }
}
