<?php

namespace App\Support;

class PublicSpaRoutes
{
    /** @var list<string> */
    private const EXACT = [
        '',
        'pricing',
        'login',
        'register',
        'password/reset',
        'support',
        'documentation',
        'faq',
        'success',
        'billing/success',
    ];

    /** @var list<string> */
    private const PREFIXES = [
        'legal/',
        'documentation/',
        'auth/verify-email/',
    ];

    public static function isPublicMarketing(string $path): bool
    {
        $path = trim($path, '/');

        if (in_array($path, self::EXACT, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isLandingHome(string $path): bool
    {
        return trim($path, '/') === '';
    }
}
