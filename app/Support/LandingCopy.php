<?php

namespace App\Support;

class LandingCopy
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public static function get(string $key, ?string $locale = null): string
    {
        $locale = self::normalizeLocale($locale ?? app()->getLocale());
        $value = self::dotGet(self::loadLocale($locale), $key);

        if ($value !== null) {
            return $value;
        }

        if ($locale !== 'en') {
            $fallback = self::dotGet(self::loadLocale('en'), $key);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return $key;
    }

    private static function normalizeLocale(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return in_array($locale, ['en', 'sk', 'es'], true) ? $locale : 'en';
    }

    /** @return array<string, mixed> */
    private static function loadLocale(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = resource_path("js/locales/{$locale}.json");
        if (! is_readable($path)) {
            self::$cache[$locale] = [];

            return self::$cache[$locale];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$cache[$locale] = is_array($decoded) ? $decoded : [];

        return self::$cache[$locale];
    }

    /** @param  array<string, mixed>  $data */
    private static function dotGet(array $data, string $key): ?string
    {
        $current = $data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_string($current) ? $current : null;
    }
}
