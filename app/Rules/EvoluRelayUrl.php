<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validate the secure relay origin used by browser transports and CSP. */
class EvoluRelayUrl implements ValidationRule
{
    /** Reject relay values that cannot be represented as a safe secure origin. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('validation.url')->translate();
        }
    }

    /** Return a canonical origin, rejecting URL components that would be discarded. */
    public static function normalize(string $value): ?string
    {
        if (strlen($value) > 512 || preg_match('~\Awss://(?<host>\[[0-9a-f:]+\]|[a-z0-9][a-z0-9.-]*)(?::(?<port>[0-9]{1,5}))?/?\z~iD', $value, $matches) !== 1) {
            return null;
        }

        $host = strtolower($matches['host']);
        $numericHost = rtrim($host, '.');
        if (str_starts_with($host, '[')) {
            if (filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return null;
            }
        } elseif (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        } elseif (preg_match('/(?:^|\.)(?:[0-9]+|0x[0-9a-f]+)$/iD', $numericHost) === 1
            && filter_var($numericHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            // Browsers reinterpret shortened, decimal, octal and hexadecimal IPs.
            return null;
        }

        $port = isset($matches['port']) ? (int) $matches['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return 'wss://'.$host.($port !== null && $port !== 443 ? ':'.$port : '');
    }
}
