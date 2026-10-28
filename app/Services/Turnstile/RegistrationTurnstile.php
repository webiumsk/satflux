<?php

namespace App\Services\Turnstile;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RegistrationTurnstile
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const ACTION = 'register';

    private const TEST_SITE_KEYS = [
        '1x00000000000000000000AA', '2x00000000000000000000AB',
        '1x00000000000000000000BB', '2x00000000000000000000BB',
        '3x00000000000000000000FF',
    ];

    private const TEST_SECRET_KEYS = [
        '1x0000000000000000000000000000000AA',
        '2x0000000000000000000000000000000AA',
        '3x0000000000000000000000000000000AA',
    ];

    /**
     * Return only the public widget settings; the verification secret stays server-side.
     *
     * @return array{enabled: bool, site_key: string|null, action: string}
     */
    public function publicConfiguration(): array
    {
        $enabled = (bool) config('turnstile.enabled');
        $siteKey = $this->setting('site_key');

        return [
            'enabled' => $enabled,
            'site_key' => $enabled && $siteKey !== '' ? $siteKey : null,
            'action' => self::ACTION,
        ];
    }

    /**
     * Verify a single-use registration token before any account or provider mutation.
     * No account input, recovery material, IP address or provider diagnostics are forwarded.
     */
    public function assertValid(Request $request): void
    {
        if (! config('turnstile.enabled')) {
            return;
        }

        $secret = $this->setting('secret_key');
        $siteKey = $this->setting('site_key');
        $hostnames = $this->hostnames();
        if ($secret === '' || $siteKey === '' || $hostnames === []
            || (app()->environment('production') && (in_array($siteKey, self::TEST_SITE_KEYS, true) || in_array($secret, self::TEST_SECRET_KEYS, true)))) {
            $this->unavailable();
        }

        $token = $request->input('turnstile_token');
        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048) {
            $this->failed();
        }

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(2)->timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->post(self::ENDPOINT, ['secret' => $secret, 'response' => $token]);
        } catch (ConnectionException) {
            $this->unavailable();
        }

        if (! $response->successful()) {
            $this->unavailable();
        }

        $result = $response->json();
        if (! is_array($result) || ! isset($result['success']) || ! is_bool($result['success'])) {
            $this->unavailable();
        }

        if ($result['success'] !== true) {
            $errors = $result['error-codes'] ?? [];
            if (! is_array($errors)) {
                $this->unavailable();
            }
            foreach ($errors as $error) {
                if (! is_string($error)) {
                    $this->unavailable();
                }
            }
            if (array_intersect($errors, ['missing-input-secret', 'invalid-input-secret', 'internal-error']) !== []) {
                $this->unavailable();
            }
            $this->failed();
        }

        $hostname = $result['hostname'] ?? null;
        $action = $result['action'] ?? null;
        if (! is_string($hostname) || $hostname === '' || ! is_string($action) || $action === '') {
            $this->unavailable();
        }
        if ($action !== self::ACTION || ! in_array($this->normalizeHostname($hostname), $hostnames, true)) {
            $this->failed();
        }
    }

    /** Read configured credentials without serializing or logging them. */
    private function setting(string $name): string
    {
        $value = config('turnstile.'.$name);

        return is_string($value) ? trim($value) : '';
    }

    /** @return list<string> Deployment-owned exact hosts, without wildcards or ports. */
    private function hostnames(): array
    {
        $configured = config('turnstile.hostnames', []);
        if (! is_array($configured)) {
            return [];
        }

        $hostnames = [];
        foreach ($configured as $hostname) {
            if (! is_string($hostname)) {
                return [];
            }
            $hostname = $this->normalizeHostname($hostname);
            if ($hostname === '' || filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                return [];
            }
            $hostnames[] = $hostname;
        }

        return array_values(array_unique($hostnames));
    }

    private function normalizeHostname(string $hostname): string
    {
        return strtolower(rtrim(trim($hostname), '.'));
    }

    /** Reject challenge failures without exposing the token or provider response. */
    private function failed(): never
    {
        $message = 'Please complete the security verification and try again.';
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'code' => 'turnstile_failed',
            'errors' => ['turnstile_token' => [$message]],
        ], 422));
    }

    /** A broken verifier must block only new registrations, without a bypass. */
    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Security verification is temporarily unavailable. Please try again.',
            'code' => 'turnstile_unavailable',
        ], 503));
    }
}
