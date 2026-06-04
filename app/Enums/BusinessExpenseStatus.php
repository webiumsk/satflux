<?php

namespace App\Enums;

enum BusinessExpenseStatus: string
{
    case Recorded = 'recorded';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
