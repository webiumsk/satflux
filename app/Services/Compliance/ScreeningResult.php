<?php

namespace App\Services\Compliance;

readonly class ScreeningResult
{
    public function __construct(
        public ScreeningStatus $status,
        public string $provider,
        public ?string $reference = null,
        public ?string $payloadHash = null,
        public ?string $decisionReason = null,
    ) {}
}
