<?php

namespace App\Services\Compliance;

use Illuminate\Support\Facades\Cache;

class LocalSanctionsScreeningProvider implements SanctionsScreeningProvider
{
    public function __construct(
        protected LocalSanctionsIndex $index,
    ) {}

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        if (! $this->index->hasEntries()) {
            return new ScreeningResult(
                status: ScreeningStatus::Error,
                provider: 'local',
                decisionReason: 'sanctions_list_not_synced',
            );
        }

        $match = $this->index->match($subject->email, $subject->name);

        if ($match['matched']) {
            return new ScreeningResult(
                status: ScreeningStatus::Hit,
                provider: 'local',
                reference: $match['reference'],
                payloadHash: $match['payload_hash'],
                decisionReason: $match['reason'],
            );
        }

        return new ScreeningResult(
            status: ScreeningStatus::Clear,
            provider: 'local',
            reference: Cache::get('compliance.sanctions_list_version'),
            payloadHash: hash('sha256', 'clear:'.$subject->email),
        );
    }
}
