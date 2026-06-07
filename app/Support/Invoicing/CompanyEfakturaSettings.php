<?php

namespace App\Support\Invoicing;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company Slovak e-faktura / SAPI-SK credentials (stored in app_settings).
 */
final class CompanyEfakturaSettings
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values = []) {}

    public static function fromCompany(Company $company): self
    {
        return new self(CompanyAppSettings::from($company->app_settings)->toArray());
    }

    public function enabled(): bool
    {
        return (bool) ($this->values['efaktura_enabled'] ?? false);
    }

    public function autoSend(): bool
    {
        return (bool) ($this->values['efaktura_auto_send'] ?? false);
    }

    public function provider(): string
    {
        return (string) ($this->values['efaktura_provider'] ?? 'sapi_sk');
    }

    public function peppolParticipantId(): ?string
    {
        $id = trim((string) ($this->values['efaktura_peppol_participant_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    public function sapiClientId(): ?string
    {
        $id = trim((string) ($this->values['efaktura_sapi_client_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    public function sapiClientSecret(): ?string
    {
        $encrypted = $this->values['efaktura_sapi_client_secret_encrypted'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function configured(): bool
    {
        return $this->enabled()
            && $this->peppolParticipantId() !== null
            && $this->sapiClientId() !== null
            && $this->sapiClientSecret() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'efaktura_enabled' => $this->enabled(),
            'efaktura_auto_send' => $this->autoSend(),
            'efaktura_provider' => $this->provider(),
            'efaktura_peppol_participant_id' => $this->peppolParticipantId(),
            'efaktura_sapi_client_id' => $this->sapiClientId(),
            'efaktura_sapi_client_secret_set' => $this->sapiClientSecret() !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeIncoming(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ([
            'efaktura_enabled',
            'efaktura_auto_send',
            'efaktura_provider',
            'efaktura_peppol_participant_id',
            'efaktura_sapi_client_id',
        ] as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = $incoming[$key];
            }
        }

        if (array_key_exists('efaktura_sapi_client_secret', $incoming)) {
            $secret = $incoming['efaktura_sapi_client_secret'];
            if ($secret === null || $secret === '') {
                $merged['efaktura_sapi_client_secret_encrypted'] = null;
            } else {
                $merged['efaktura_sapi_client_secret_encrypted'] = Crypt::encryptString((string) $secret);
            }
        }

        return $merged;
    }
}
