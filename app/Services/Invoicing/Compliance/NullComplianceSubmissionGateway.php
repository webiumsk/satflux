<?php

namespace App\Services\Invoicing\Compliance;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Support\Invoicing\ComplianceSubmissionResult;

/**
 * Placeholder until a Peppol/CTC partner is wired.
 */
class NullComplianceSubmissionGateway implements ComplianceSubmissionGateway
{
    public function provider(): string
    {
        return 'noop';
    }

    public function supports(BusinessDocument $document): bool
    {
        return false;
    }

    public function submit(BusinessDocument $document): ComplianceSubmissionResult
    {
        return new ComplianceSubmissionResult(
            status: ComplianceSubmissionStatus::Skipped,
            message: 'Compliance submission is not configured.',
        );
    }
}
