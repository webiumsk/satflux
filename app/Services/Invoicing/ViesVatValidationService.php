<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\ViesValidationResult;
use App\Support\Invoicing\ViesVatNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViesVatValidationService
{
    public function validate(string $vatNumber, ?string $defaultCountryCode = null): ViesValidationResult
    {
        $parsed = ViesVatNumber::parse($vatNumber, $defaultCountryCode);

        if ($parsed === null) {
            return new ViesValidationResult(
                valid: false,
                countryCode: strtoupper($defaultCountryCode ?? ''),
                vatNumber: trim($vatNumber),
                errorCode: 'INVALID_FORMAT',
                errorMessage: 'VAT number format is invalid for VIES.',
            );
        }

        $countryCode = $parsed['country_code'];
        $number = $parsed['vat_number'];

        if (! $this->isSupportedCountry($countryCode)) {
            return new ViesValidationResult(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $number,
                errorCode: 'UNSUPPORTED_COUNTRY',
                errorMessage: 'Country is not supported by the EU VIES service.',
            );
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get($this->endpoint(), [
                    'countryCode' => $countryCode,
                    'vatNumber' => $number,
                ]);
        } catch (\Throwable $e) {
            Log::warning('VIES validation request failed', [
                'country' => $countryCode,
                'vat' => $number,
                'message' => $e->getMessage(),
            ]);

            return new ViesValidationResult(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $number,
                errorCode: 'SERVICE_UNAVAILABLE',
                errorMessage: 'VIES service is temporarily unavailable.',
            );
        }

        if (! $response->successful()) {
            return new ViesValidationResult(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $number,
                errorCode: 'HTTP_'.$response->status(),
                errorMessage: 'VIES service returned an error.',
            );
        }

        $payload = $response->json();
        $userError = is_array($payload) ? ($payload['userError'] ?? null) : null;

        if (is_string($userError) && $userError !== '' && strtoupper($userError) !== 'VALID') {
            return new ViesValidationResult(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $number,
                errorCode: strtoupper($userError),
                errorMessage: $this->userErrorMessage($userError),
                requestDate: is_array($payload) ? ($payload['requestDate'] ?? null) : null,
            );
        }

        $valid = (bool) (is_array($payload) ? ($payload['valid'] ?? false) : false);

        return new ViesValidationResult(
            valid: $valid,
            countryCode: $countryCode,
            vatNumber: $number,
            name: is_array($payload) ? ($payload['name'] ?? null) : null,
            address: is_array($payload) ? ($payload['address'] ?? null) : null,
            requestDate: is_array($payload) ? ($payload['requestDate'] ?? null) : null,
            errorCode: $valid ? null : 'NOT_REGISTERED',
            errorMessage: $valid ? null : 'VAT number is not registered in VIES.',
        );
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('services.vies.base_url'), '/').'/check-vat-number';
    }

    protected function timeout(): int
    {
        return max(3, (int) config('services.vies.timeout', 15));
    }

    protected function isSupportedCountry(string $countryCode): bool
    {
        return in_array($countryCode, [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR',
            'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO',
            'SE', 'SI', 'SK', 'XI',
        ], true);
    }

    protected function userErrorMessage(string $code): string
    {
        return match (strtoupper($code)) {
            'INVALID_INPUT' => 'Invalid VAT number input.',
            'INVALID_REQUESTER_INFO' => 'Invalid requester information.',
            'SERVICE_UNAVAILABLE', 'MS_UNAVAILABLE', 'TIMEOUT' => 'VIES service is temporarily unavailable.',
            'GLOBAL_MAX_CONCURRENT_REQ', 'MS_MAX_CONCURRENT_REQ' => 'VIES rate limit reached. Try again later.',
            default => 'VIES validation failed.',
        };
    }
}
