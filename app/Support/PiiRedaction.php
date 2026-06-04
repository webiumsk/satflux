<?php

namespace App\Support;

/**
 * Hash or mask PII for logs and audit metadata.
 */
final class PiiRedaction
{
    public static function emailHash(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return 'sha256:'.substr(hash('sha256', strtolower(trim($email))), 0, 16);
    }

    /**
     * @param  list<string>|null  $emails
     * @return list<string>|null
     */
    public static function emailListHashes(?array $emails): ?array
    {
        if ($emails === null) {
            return null;
        }

        $hashes = [];
        foreach ($emails as $email) {
            $hash = self::emailHash(is_string($email) ? $email : null);
            if ($hash !== null) {
                $hashes[] = $hash;
            }
        }

        return $hashes === [] ? null : $hashes;
    }
}
