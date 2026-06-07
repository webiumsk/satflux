<?php

namespace App\Services\Compliance\Sync;

readonly class SanctionsEntryDto
{
    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $countries
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $primaryName,
        public array $aliases = [],
        public array $countries = [],
    ) {}
}
