<?php

namespace App\Services\Compliance;

class NullSanctionsScreeningProvider implements SanctionsScreeningProvider
{
    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        return new ScreeningResult(
            status: ScreeningStatus::Skipped,
            provider: 'null',
        );
    }
}
