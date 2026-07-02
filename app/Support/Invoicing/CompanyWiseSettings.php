<?php

namespace App\Support\Invoicing;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company Wise API credentials (stored in app_settings).
 */
final class CompanyWiseSettings
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values = []) {}

    public static function fromCompany(Company $company): self
    {
        return new self(CompanyAppSettings::from($company->app_settings)->toArray());
    }

    public function apiToken(): ?string
    {
        $plain = $this->values['wise_api_token'] ?? null;
        if (is_string($plain) && $plain !== '') {
            return $plain;
        }

        $encrypted = $this->values['wise_api_token_encrypted'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function profileId(): ?int
    {
        $id = $this->values['wise_profile_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function balanceId(): ?int
    {
        $id = $this->values['wise_balance_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function lastSyncAt(): ?string
    {
        $at = $this->values['wise_last_sync_at'] ?? null;

        return is_string($at) && $at !== '' ? $at : null;
    }

    public function configured(): bool
    {
        return $this->apiToken() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'wise_token_set' => $this->apiToken() !== null,
            'wise_profile_id' => $this->profileId(),
            'wise_balance_id' => $this->balanceId(),
            'wise_last_sync_at' => $this->lastSyncAt(),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeIncoming(array $current, array $incoming): array
    {
        $merged = $current;

        foreach (['wise_profile_id', 'wise_balance_id'] as $key) {
            if (array_key_exists($key, $incoming)) {
                $value = $incoming[$key];
                $merged[$key] = $value === null || $value === '' ? null : (int) $value;
            }
        }

        if (array_key_exists('wise_api_token', $incoming)) {
            $token = is_string($incoming['wise_api_token'])
                ? trim($incoming['wise_api_token'])
                : $incoming['wise_api_token'];
            if ($token === null || $token === '') {
                $merged['wise_api_token_encrypted'] = null;
            } else {
                $merged['wise_api_token_encrypted'] = Crypt::encryptString((string) $token);
            }
            unset($merged['wise_api_token']);
        }

        if (array_key_exists('wise_last_sync_at', $incoming)) {
            $merged['wise_last_sync_at'] = $incoming['wise_last_sync_at'];
        }

        return $merged;
    }
}
