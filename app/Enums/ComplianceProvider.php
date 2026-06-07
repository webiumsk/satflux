<?php

namespace App\Enums;

enum ComplianceProvider: string
{
    case Peppol = 'peppol';
    case Ctc = 'ctc';

    public function label(): string
    {
        return match ($this) {
            self::Peppol => 'Peppol',
            self::Ctc => 'CTC',
        };
    }
}
