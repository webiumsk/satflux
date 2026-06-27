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
    private const LEGAL_SLUGS = ['terms', 'privacy', 'imprint', 'dpa'];

    public static function isPublicMarketing(string $path): bool
    {
        $path = trim($path, '/');

        if (in_array($path, self::EXACT, true)) {
            return true;
        }

        if (preg_match('#^legal/([^/]+)$#', $path, $m) && in_array($m[1], self::LEGAL_SLUGS, true)) {
            return true;
        }

        if ($path === 'documentation' || preg_match('#^documentation/[^/]+$#', $path)) {
            return true;
        }

        $parts = explode('/', $path);
        if (
            count($parts) === 4
            && $parts[0] === 'auth'
            && $parts[1] === 'verify-email'
            && $parts[2] !== ''
            && $parts[3] !== ''
        ) {
            return true;
        }

        return false;
    }

    public static function isLandingHome(string $path): bool
    {
        return trim($path, '/') === '';
    }
}
