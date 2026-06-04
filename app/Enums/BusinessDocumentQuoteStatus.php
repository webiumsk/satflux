<?php

namespace App\Enums;

enum BusinessDocumentQuoteStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public static function openForApproval(): array
    {
        return [self::Pending];
    }
}
