<?php

namespace App\Enums;

enum BankImportSource: string
{
    case Csv = 'csv';
    case Camt053 = 'camt053';
    case Manual = 'manual';
    case Email = 'email';
    case Wise = 'wise';
}
