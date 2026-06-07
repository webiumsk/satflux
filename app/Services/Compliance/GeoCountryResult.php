<?php

namespace App\Services\Compliance;

readonly class GeoCountryResult
{
    public function __construct(
        public ?string $countryCode,
        public string $source,
    ) {}
}
