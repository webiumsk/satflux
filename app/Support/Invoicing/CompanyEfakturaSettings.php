<?php

namespace App\Support\Invoicing;

use App\Models\Company;
use App\Support\Invoicing\Efaktura\PeppolParticipantId;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Per-company Slovak e-faktura / SAPI-SK credentials (stored in app_settings).
 */
final class CompanyEfakturaSettings
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public array $values = [],
        protected ?string $derivedPeppolParticipantId = null,
        protected bool $allowBaseUrlFallback = true,
    ) {}

    public static function fromCompany(Company $company): self
    {
        return new self(
            CompanyAppSettings::from($company->app_settings)->toArray(),
            PeppolParticipantId::fromCompany($company),
        );
    }

    public function enabled(): bool
    {
        return (bool) ($this->values['efaktura_enabled'] ?? false);
    }

    public function autoSend(): bool
    {
        return (bool) ($this->values['efaktura_auto_send'] ?? false);
    }

    public function inboundEnabled(): bool
    {
        return (bool) ($this->values['efaktura_inbound_enabled'] ?? false);
    }

    public function inboundUsesSharedConnection(): bool
    {
        return (bool) ($this->values['efaktura_inbound_use_shared_connection'] ?? true);
    }

    /** Resolve the receiving credentials without falling back from an incomplete split. */
    public function forInbound(): self
    {
        if ($this->inboundUsesSharedConnection()) {
            return clone $this;
        }

        $values = $this->values;
        $values['efaktura_provider'] = $values['efaktura_inbound_provider'] ?? 'sapi_sk';
        foreach (['base_url', 'client_id', 'client_secret', 'client_secret_encrypted'] as $suffix) {
            unset($values['efaktura_sapi_'.$suffix]);
            if (array_key_exists('efaktura_inbound_sapi_'.$suffix, $this->values)) {
                $values['efaktura_sapi_'.$suffix] = $this->values['efaktura_inbound_sapi_'.$suffix];
            }
        }
        $values['efaktura_connection_tested_at'] = $values['efaktura_inbound_connection_tested_at'] ?? null;

        return new self($values, $this->derivedPeppolParticipantId, allowBaseUrlFallback: false);
    }

    public function provider(): string
    {
        return (string) ($this->values['efaktura_provider'] ?? 'sapi_sk');
    }

    /** The explicitly stored participant ID only (settings form value). */
    public function explicitPeppolParticipantId(): ?string
    {
        $id = trim((string) ($this->values['efaktura_peppol_participant_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /** Auto-derived from the company DIČ (0245) or Slovak IČO (0158). */
    public function derivedPeppolParticipantId(): ?string
    {
        return $this->derivedPeppolParticipantId;
    }

    /**
     * Effective sender ID: the explicit override wins, otherwise the value
     * derived from the company registration data - merchants never have to
     * learn the scheme syntax when their DIČ/IČO is filled in.
     */
    public function peppolParticipantId(): ?string
    {
        return $this->explicitPeppolParticipantId() ?? $this->derivedPeppolParticipantId;
    }

    public function sapiBaseUrl(): ?string
    {
        $url = trim((string) ($this->values['efaktura_sapi_base_url'] ?? ''));
        if ($url !== '') {
            return rtrim($url, '/');
        }

        $fallback = $this->allowBaseUrlFallback ? rtrim((string) config('efaktura.providers.sapi_sk.base_url'), '/') : '';

        return $fallback !== '' ? $fallback : null;
    }

    public function sapiClientId(): ?string
    {
        $id = trim((string) ($this->values['efaktura_sapi_client_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    public function sapiClientSecret(): ?string
    {
        $plain = $this->values['efaktura_sapi_client_secret'] ?? null;
        if (is_string($plain) && $plain !== '') {
            return $plain;
        }

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

    /** Server-only comparison; never expose or log this credential fingerprint. */
    public function connectionFingerprint(): string
    {
        return hash('sha256', serialize([
            $this->enabled(), $this->inboundEnabled(), $this->provider(),
            $this->sapiBaseUrl(), $this->sapiClientId(), $this->sapiClientSecret(),
            $this->peppolParticipantId(), $this->derivedPeppolParticipantId(),
        ]));
    }

    /** Sending identity excludes independent receiving preferences. Never serialize. */
    public function outboundConnectionFingerprint(): string
    {
        return hash('sha256', serialize([
            'outbound-v2', $this->enabled(), $this->provider(),
            $this->sapiBaseUrl(), $this->sapiClientId(), $this->sapiClientSecret(),
            $this->peppolParticipantId(), $this->derivedPeppolParticipantId(),
        ]));
    }

    /** Accept old attempts with either receiving flag, retaining every sender binding. */
    public function matchesOutboundConnectionFingerprint(string $fingerprint): bool
    {
        if (hash_equals($this->outboundConnectionFingerprint(), $fingerprint)) {
            return true;
        }
        foreach ([false, true] as $inbound) {
            $legacy = clone $this;
            $legacy->values['efaktura_inbound_enabled'] = $inbound;
            if (hash_equals($legacy->connectionFingerprint(), $fingerprint)) {
                return true;
            }
        }

        return false;
    }

    /** Receiving generation includes the selected source, independent of sender changes. */
    public function inboundConnectionFingerprint(): string
    {
        return hash('sha256', serialize([
            $this->inboundUsesSharedConnection(), $this->forInbound()->connectionFingerprint(),
        ]));
    }

    public const ENTERPRISE_PRIVATE_FIELDS = [
        'efaktura_epostman_api_key', 'efaktura_epostman_api_key_encrypted',
        'efaktura_epostman_status_generation', 'efaktura_epostman_status_last_scan_at',
    ];

    public static function withoutEnterpriseSecrets(array $settings): array
    {
        return array_diff_key($settings, array_flip(array_merge(self::ENTERPRISE_PRIVATE_FIELDS, ['efaktura_epostman_api_key_set'])));
    }

    public function enterpriseApiKey(): ?string
    {
        $encrypted = $this->values['efaktura_epostman_api_key_encrypted'] ?? null;
        try {
            return is_string($encrypted) && $encrypted !== '' ? Crypt::decryptString($encrypted) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function enterpriseOrigin(): ?string
    {
        $parts = parse_url((string) $this->sapiBaseUrl());
        if ($this->provider() !== 'sapi_sk' || ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), ['postman.slovakodata.com', 'testpostman.slovakodata.com'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['port'] ?? 443, [443], true)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return 'https://'.strtolower($parts['host']);
    }

    public function enterpriseIdentityFingerprint(): string
    {
        return hash('sha256', serialize([$this->provider(), $this->enterpriseOrigin() ?? $this->sapiBaseUrl(),
            $this->peppolParticipantId(), $this->derivedPeppolParticipantId()]));
    }

    /** Independent from the pinned send fingerprint; never serialized. */
    public function enterpriseStatusFingerprint(): string
    {
        return hash('sha256', serialize([$this->enabled(), $this->enterpriseIdentityFingerprint(),
            $this->values['efaktura_epostman_status_generation'] ?? null, $this->enterpriseApiKey()]));
    }

    public function enterpriseConfigured(): bool
    {
        return $this->enabled() && $this->enterpriseOrigin() !== null
            && $this->peppolParticipantId() !== null && $this->enterpriseApiKey() !== null;
    }

    public static function clearEnterpriseKey(array $settings): array
    {
        unset($settings['efaktura_epostman_api_key']);
        $settings['efaktura_epostman_api_key_encrypted'] = null;
        $settings['efaktura_epostman_status_generation'] = (string) Str::uuid();

        return $settings;
    }

    public function configured(): bool
    {
        return $this->enabled()
            && $this->peppolParticipantId() !== null
            && $this->sapiBaseUrl() !== null
            && $this->sapiClientId() !== null
            && $this->sapiClientSecret() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'efaktura_epostman_api_key_set' => is_string($this->values['efaktura_epostman_api_key_encrypted'] ?? null) && $this->values['efaktura_epostman_api_key_encrypted'] !== '',
            'efaktura_enabled' => $this->enabled(),
            'efaktura_auto_send' => $this->autoSend(),
            'efaktura_inbound_enabled' => $this->inboundEnabled(),
            'efaktura_inbound_use_shared_connection' => $this->inboundUsesSharedConnection(),
            'efaktura_inbound_provider' => $this->values['efaktura_inbound_provider'] ?? 'sapi_sk',
            'efaktura_inbound_sapi_base_url' => $this->values['efaktura_inbound_sapi_base_url'] ?? null,
            'efaktura_inbound_sapi_client_id' => $this->values['efaktura_inbound_sapi_client_id'] ?? null,
            'efaktura_inbound_sapi_client_secret_set' => ! $this->inboundUsesSharedConnection()
                ? $this->forInbound()->sapiClientSecret() !== null
                : $this->separateInboundSecret() !== null,
            'efaktura_inbound_connection_tested_at' => $this->values['efaktura_inbound_connection_tested_at'] ?? null,
            'efaktura_provider' => $this->provider(),
            'efaktura_sapi_base_url' => $this->sapiBaseUrl(),
            // The form shows only the explicit override; the derived default
            // travels separately so the UI can hint "we will use 0245:...".
            'efaktura_peppol_participant_id' => $this->explicitPeppolParticipantId(),
            'efaktura_peppol_participant_id_derived' => $this->derivedPeppolParticipantId,
            'efaktura_sapi_client_id' => $this->sapiClientId(),
            'efaktura_sapi_client_secret_set' => $this->sapiClientSecret() !== null,
            'efaktura_connection_tested_at' => $this->values['efaktura_connection_tested_at'] ?? null,
            'efaktura_inbound_last_poll_at' => $this->values['efaktura_inbound_last_poll_at'] ?? null,
            'efaktura_inbound_last_poll_stats' => $this->values['efaktura_inbound_last_poll_stats'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeIncoming(array $current, array $incoming, ?string $derivedParticipantId = null): array
    {
        // Only the write-only plaintext input may replace a stored credential.
        unset($incoming['efaktura_epostman_api_key_encrypted'], $incoming['efaktura_epostman_status_generation'], $incoming['efaktura_epostman_status_last_scan_at'], $incoming['efaktura_epostman_api_key_set']);
        // Poll/test evidence belongs to completed server operations, never a settings save.
        unset($incoming['efaktura_sapi_client_secret_encrypted'], $incoming['efaktura_inbound_sapi_client_secret_encrypted'], $incoming['efaktura_inbound_sapi_client_secret_set'], $incoming['efaktura_inbound_last_poll_at'], $incoming['efaktura_inbound_last_poll_stats'], $incoming['efaktura_connection_tested_at'], $incoming['efaktura_inbound_connection_tested_at']);
        $merged = array_merge($current, $incoming);

        foreach ([
            'efaktura_enabled',
            'efaktura_auto_send',
            'efaktura_inbound_enabled',
            'efaktura_provider',
            'efaktura_peppol_participant_id',
            'efaktura_sapi_client_id',
        ] as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = $incoming[$key];
            }
        }

        if (array_key_exists('efaktura_sapi_base_url', $incoming)) {
            $url = trim((string) $incoming['efaktura_sapi_base_url']);
            $merged['efaktura_sapi_base_url'] = $url !== '' ? rtrim($url, '/') : null;
        }

        $savedEnterpriseKey = $current['efaktura_epostman_api_key_encrypted'] ?? null;
        if (is_string($savedEnterpriseKey) && $savedEnterpriseKey !== ''
            && (! array_key_exists('efaktura_epostman_api_key', $incoming) || $incoming['efaktura_epostman_api_key'] !== null)
            && trim((string) ($merged['efaktura_sapi_base_url'] ?? '')) === ''
            && (new self($current))->enterpriseOrigin() !== null
            && (new self($current))->enterpriseOrigin() === (new self($merged))->enterpriseOrigin()) {
            // Pin before comparing SAPI credentials too: equivalent host casing
            // or :443 must not invalidate an existing send/retry fingerprint.
            $merged['efaktura_sapi_base_url'] = (new self($current))->sapiBaseUrl();
        }

        if (! array_key_exists('efaktura_sapi_client_secret', $incoming) && ((array_key_exists('efaktura_sapi_base_url', $incoming) && (new self($merged))->sapiBaseUrl() !== (new self($current))->sapiBaseUrl()) || (array_key_exists('efaktura_sapi_client_id', $incoming) && ($merged['efaktura_sapi_client_id'] ?? null) !== ($current['efaktura_sapi_client_id'] ?? null)))) {
            $merged['efaktura_sapi_client_secret_encrypted'] = null;
            unset($merged['efaktura_sapi_client_secret']);
        }

        $secretChanged = false;
        if (array_key_exists('efaktura_sapi_client_secret', $incoming)) {
            $currentSecret = (new self($current))->sapiClientSecret();
            $secret = is_string($incoming['efaktura_sapi_client_secret'])
                ? trim($incoming['efaktura_sapi_client_secret'])
                : $incoming['efaktura_sapi_client_secret'];
            // A bridge save resends its saved secret. Preserve evidence only when
            // both nonempty values are readable and equal, independent of ciphertext IVs.
            $secretChanged = $currentSecret === null || $currentSecret === ''
                || ! is_string($secret) || $secret === '' || ! hash_equals($currentSecret, $secret);
            if ($secret === null || $secret === '') {
                $merged['efaktura_sapi_client_secret_encrypted'] = null;
            } else {
                $merged['efaktura_sapi_client_secret_encrypted'] = Crypt::encryptString((string) $secret);
            }
            unset($merged['efaktura_sapi_client_secret']);
        }

        foreach (['efaktura_provider', 'efaktura_sapi_base_url', 'efaktura_sapi_client_id', 'efaktura_peppol_participant_id', 'efaktura_enabled'] as $connectionField) {
            if (array_key_exists($connectionField, $incoming) && ($merged[$connectionField] ?? null) !== ($current[$connectionField] ?? null)) {
                unset($merged['efaktura_connection_tested_at']);
            }
        }
        if ($secretChanged) {
            unset($merged['efaktura_connection_tested_at']);
        }

        $merged = self::mergeInboundConnection($current, $incoming, $merged, $derivedParticipantId, $secretChanged);

        $identityChanged = (new self($current, $derivedParticipantId))->enterpriseIdentityFingerprint() !== (new self($merged, $derivedParticipantId))->enterpriseIdentityFingerprint();
        if ($identityChanged) {
            $merged = self::clearEnterpriseKey($merged);
        }
        if (array_key_exists('efaktura_epostman_api_key', $incoming)) {
            $key = $incoming['efaktura_epostman_api_key'];
            if ($key !== null && (! is_string($key) || preg_match('/^[\x21-\x7E]{1,4096}$/D', $key) !== 1)) {
                throw ValidationException::withMessages(['efaktura_epostman_api_key' => ['Enter a valid Enterprise API key.']]);
            }
            $merged = self::clearEnterpriseKey($merged);
            if ($key !== null) {
                // Bind an implicit global origin explicitly when saving its key;
                // a later global fallback change must never move that credential.
                if (trim((string) ($merged['efaktura_sapi_base_url'] ?? '')) === '') {
                    $merged['efaktura_sapi_base_url'] = (new self($merged))->sapiBaseUrl();
                }
                $merged['efaktura_epostman_api_key_encrypted'] = Crypt::encryptString($key);
            }
        }
        // A surviving key must stay tied to today's effective origin even if
        // an equivalent explicit URL is cleared without replacing the key.
        if (is_string($merged['efaktura_epostman_api_key_encrypted'] ?? null)
            && $merged['efaktura_epostman_api_key_encrypted'] !== ''
            && trim((string) ($merged['efaktura_sapi_base_url'] ?? '')) === '') {
            $merged['efaktura_sapi_base_url'] = (new self($current))->sapiBaseUrl();
        }
        unset($merged['efaktura_sapi_client_secret'], $merged['efaktura_inbound_sapi_client_secret'], $merged['efaktura_epostman_api_key']);

        return $merged;
    }

    public function separateInboundSecret(): ?string
    {
        $separate = clone $this;
        $separate->values['efaktura_inbound_use_shared_connection'] = false;

        return $separate->forInbound()->sapiClientSecret();
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    protected static function mergeInboundConnection(array $current, array $incoming, array $merged, ?string $derivedParticipantId, bool $sharedSecretChanged): array
    {
        $before = new self($current, $derivedParticipantId);
        if (array_key_exists('efaktura_inbound_sapi_base_url', $incoming)) {
            $url = rtrim(trim((string) $incoming['efaktura_inbound_sapi_base_url']), '/');
            $merged['efaktura_inbound_sapi_base_url'] = $url !== '' ? $url : null;
        }

        $oldSeparate = clone $before;
        $oldSeparate->values['efaktura_inbound_use_shared_connection'] = false;
        $newSeparate = new self(array_merge($merged, ['efaktura_inbound_use_shared_connection' => false]), $derivedParticipantId);
        if (! array_key_exists('efaktura_inbound_sapi_client_secret', $incoming)
            && ($oldSeparate->forInbound()->sapiBaseUrl() !== $newSeparate->forInbound()->sapiBaseUrl()
                || $oldSeparate->forInbound()->sapiClientId() !== $newSeparate->forInbound()->sapiClientId()
                || $oldSeparate->forInbound()->provider() !== $newSeparate->forInbound()->provider())) {
            $merged['efaktura_inbound_sapi_client_secret_encrypted'] = null;
            unset($merged['efaktura_inbound_sapi_client_secret']);
        }
        $secretChanged = false;
        if (array_key_exists('efaktura_inbound_sapi_client_secret', $incoming)) {
            $secret = $incoming['efaktura_inbound_sapi_client_secret'];
            $secret = is_string($secret) ? trim($secret) : $secret;
            $currentSecret = $oldSeparate->forInbound()->sapiClientSecret();
            $secretChanged = $currentSecret === null || $currentSecret === ''
                || ! is_string($secret) || $secret === '' || ! hash_equals($currentSecret, $secret);
            $merged['efaktura_inbound_sapi_client_secret_encrypted'] = is_string($secret) && $secret !== ''
                ? Crypt::encryptString($secret) : null;
            unset($merged['efaktura_inbound_sapi_client_secret']);
        }
        $after = new self($merged, $derivedParticipantId);
        $newSeparate = new self(array_merge($merged, ['efaktura_inbound_use_shared_connection' => false]), $derivedParticipantId);
        if ($secretChanged || ! hash_equals($oldSeparate->inboundConnectionFingerprint(), $newSeparate->inboundConnectionFingerprint())) {
            unset($merged['efaktura_inbound_connection_tested_at']);
        }
        if (($after->inboundUsesSharedConnection() ? $sharedSecretChanged : $secretChanged)
            || ! hash_equals($before->inboundConnectionFingerprint(), $after->inboundConnectionFingerprint())) {
            unset($merged['efaktura_inbound_last_poll_at'], $merged['efaktura_inbound_last_poll_stats']);
        }

        return $merged;
    }
}
