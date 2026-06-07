<?php

namespace App\Services\Compliance;

enum ScreeningStatus: string
{
    case Clear = 'clear';
    case Hit = 'hit';
    case Error = 'error';
    case Skipped = 'skipped';
}
