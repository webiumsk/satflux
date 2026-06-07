<?php

namespace App\Services\Compliance;

readonly class ScreeningSubject
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?string $countryCode = null,
    ) {}
}
