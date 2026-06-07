<?php

namespace App\Services\Compliance;

interface SanctionsScreeningProvider
{
    public function screen(ScreeningSubject $subject): ScreeningResult;
}
