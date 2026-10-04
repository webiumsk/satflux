<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, int>|null $period_floors Counter floor per numbering period (see DocumentSequenceService::syncPeriod).
 */
class CompanyDocumentSequence extends Model
{
    protected $fillable = [
        'company_id',
        'document_type',
        'name',
        'format',
        'reset_period',
        'is_default',
        'period_key',
        'last_number',
        'period_floors',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'last_number' => 'integer',
            'period_floors' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
