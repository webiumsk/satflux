<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'last_number' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
