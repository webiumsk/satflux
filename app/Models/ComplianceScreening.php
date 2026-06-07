<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceScreening extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_email',
        'subject_name',
        'ip_address',
        'country_code',
        'geo_blocked',
        'screening_provider',
        'screening_status',
        'screening_reference',
        'screening_payload_hash',
        'decision',
        'decision_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'geo_blocked' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
