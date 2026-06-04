<?php

namespace App\Contracts\Invoicing;

use App\Models\BusinessDocument;
use App\Support\Invoicing\ComplianceSubmissionResult;

/**
 * Gateway for government or network submission (Peppol, CTC, …).
 */
interface ComplianceSubmissionGateway
{
    public function provider(): string;

    public function supports(BusinessDocument $document): bool;

    public function submit(BusinessDocument $document): ComplianceSubmissionResult;
}
