<?php

namespace App\Enums;

enum BusinessDocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
