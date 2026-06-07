<?php

namespace App\Models;

use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocumentCompliance extends Model
{
    use HasUuids;

    protected $table = 'business_document_compliance';

    protected $fillable = [
        'business_document_id',
        'provider',
        'status',
        'external_id',
        'response_payload',
        'qr_payload',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ComplianceProvider::class,
            'status' => ComplianceSubmissionStatus::class,
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
