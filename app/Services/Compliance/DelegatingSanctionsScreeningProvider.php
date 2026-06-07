<?php

namespace App\Services\Compliance;

class DelegatingSanctionsScreeningProvider implements SanctionsScreeningProvider
{
    public function __construct(
        protected NullSanctionsScreeningProvider $nullProvider,
        protected LocalSanctionsScreeningProvider $localProvider,
    ) {}

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        if (! config('compliance.list_screening_enabled')) {
            return $this->nullProvider->screen($subject);
        }

        return $this->localProvider->screen($subject);
    }
}
