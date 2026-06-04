<?php

namespace App\Enums;

enum ComplianceSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
