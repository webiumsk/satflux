<?php

namespace App\Support\Invoicing;

final class ViesValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $countryCode,
        public readonly string $vatNumber,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $requestDate = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'country_code' => $this->countryCode,
            'vat_number' => $this->vatNumber,
            'name' => $this->name,
            'address' => $this->address,
            'request_date' => $this->requestDate,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}
