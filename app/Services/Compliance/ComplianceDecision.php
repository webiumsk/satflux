<?php

namespace App\Services\Compliance;

enum ComplianceDecision: string
{
    case Allowed = 'allowed';
    case Blocked = 'blocked';
    case PendingReview = 'pending_review';
}
