<?php

namespace App\Enums;

enum BankTransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
