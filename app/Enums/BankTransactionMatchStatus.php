<?php

namespace App\Enums;

enum BankTransactionMatchStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';
}
