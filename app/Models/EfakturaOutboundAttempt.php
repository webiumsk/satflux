<?php

namespace App\Models;

use App\Enums\ComplianceSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Server-only request custody and retry identity; never serialize this model.
 *
 * @property ComplianceSubmissionStatus $status
 * @property Carbon $retry_until
 * @property array<string, mixed>|null $response_payload
 */
class EfakturaOutboundAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['connection_fingerprint', 'content_sha256', 'request_encrypted'];

    protected function casts(): array
    {
        return ['status' => ComplianceSubmissionStatus::class, 'response_payload' => 'array', 'retry_until' => 'datetime'];
    }
}
