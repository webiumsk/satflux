<?php

namespace App\Support\Invoicing;

use App\Enums\ComplianceSubmissionStatus;

final class ComplianceSubmissionResult
{
    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function __construct(
        public readonly ComplianceSubmissionStatus $status,
        public readonly ?string $externalId = null,
        public readonly ?array $responsePayload = null,
        public readonly ?string $qrPayload = null,
        public readonly ?string $message = null,
    ) {}
}
