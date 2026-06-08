<?php

namespace App\Support\Invoicing\Efaktura;

use App\Enums\ComplianceSubmissionStatus;

final class SapiSkDocumentStatusMapper
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromProviderPayload(?array $payload): ?ComplianceSubmissionStatus
    {
        if ($payload === null) {
            return null;
        }

        $raw = $payload['status']
            ?? $payload['deliveryStatus']
            ?? $payload['documentStatus']
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return self::fromProviderStatus($raw);
    }

    public static function fromProviderStatus(string $status): ?ComplianceSubmissionStatus
    {
        $normalized = strtoupper(trim(str_replace([' ', '-'], '_', $status)));

        return match ($normalized) {
            'ACCEPTED', 'APPROVED', 'DELIVERED', 'COMPLETED', 'SUCCESS', 'SUCCEEDED', 'OK' => ComplianceSubmissionStatus::Approved,
            'REJECTED' => ComplianceSubmissionStatus::Rejected,
            'FAILED', 'FAILURE', 'ERROR', 'DEAD_LETTER', 'DEADLETTER', 'UNDELIVERABLE' => ComplianceSubmissionStatus::Failed,
            'QUEUED', 'PROCESSING', 'PENDING', 'SUBMITTED', 'SENT', 'IN_PROGRESS', 'RETRYING' => ComplianceSubmissionStatus::Submitted,
            'SKIPPED' => ComplianceSubmissionStatus::Skipped,
            default => null,
        };
    }
}
