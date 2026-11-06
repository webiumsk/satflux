<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validate the bounded, ordered relay selection shared by transports and CSP. */
class EvoluRelays implements ValidationRule
{
    public const MAX_RELAYS = 8;

    public const MAX_TOTAL_URL_LENGTH = 2048;

    /** Reject malformed, duplicate or oversized selections before any profile mutation. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalize($value) === null) {
            $fail('validation.array')->translate();
        }
    }

    /**
     * Preserve explicit empty/off selections while canonicalizing safe relay origins.
     *
     * @return list<array{url: string, enabled: bool}>|null
     */
    public static function normalize(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_RELAYS) {
            return null;
        }

        $normalized = [];
        $seen = [];
        $totalLength = 0;
        foreach ($value as $relay) {
            if (! is_array($relay) || count($relay) !== 2
                || ! isset($relay['url']) || ! is_string($relay['url'])
                || ! array_key_exists('enabled', $relay) || ! is_bool($relay['enabled'])) {
                return null;
            }
            $url = EvoluRelayUrl::normalize($relay['url']);
            if ($url === null || isset($seen[$url])) {
                return null;
            }
            $totalLength += strlen($url);
            if ($totalLength > self::MAX_TOTAL_URL_LENGTH) {
                return null;
            }
            $seen[$url] = true;
            $normalized[] = ['url' => $url, 'enabled' => $relay['enabled']];
        }

        return $normalized;
    }
}
