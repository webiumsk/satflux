<?php

namespace App\Models;

use App\Enums\ComplianceSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EphemeralEfakturaSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'bridge_company_id',
        'evolu_document_id',
        'provider',
        'status',
        'external_id',
        'message',
        'response_payload',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplianceSubmissionStatus::class,
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bridgeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'bridge_company_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiRow(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status?->value ?? (string) $this->getRawOriginal('status'),
            'external_id' => $this->external_id,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'response_payload' => $this->response_payload,
            'message' => $this->message,
        ];
    }
}
