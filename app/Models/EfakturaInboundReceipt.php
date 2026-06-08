<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EfakturaInboundReceipt extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'external_document_id',
        'business_expense_id',
        'status',
        'attachment_disk',
        'attachment_path',
        'acknowledged_at',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(BusinessExpense::class, 'business_expense_id');
    }
}
