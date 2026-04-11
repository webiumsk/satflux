<?php

namespace App\Support;

/**
 * Product/crowdfund images live under /storage/... on the public disk.
 * Config stored in BTCPay may contain absolute URLs with an old APP_URL; rewrite to the current app URL.
 */
final class SatfluxStorageUrl
{
    /**
     * If the value is an http(s) URL whose path starts with /storage/, or a path-only string starting with /storage/,
     * rebuild it using config('app.url'). Otherwise returns the input unchanged.
     */
    public static function rewriteToCurrentApp(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $trimmed = trim($value);
        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return $value;
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return $base.$trimmed;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['path']) || ! str_starts_with($parts['path'], '/storage/')) {
            return $value;
        }

        $path = $parts['path'];
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $base.$path.$query.$fragment;
    }
}
