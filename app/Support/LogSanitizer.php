<?php

namespace App\Support;

/**
 * Masking helpers for log context (P1 phase 7): logs must stay useful for
 * correlation without spelling out PII. Prefer logging user_id and pass
 * personal fields through these helpers.
 */
class LogSanitizer
{
    /**
     * "samuel@example.com" -> "s***@example.com". The domain stays readable
     * (deliverability debugging); the local part is reduced to its first
     * character. Non-email input is fully masked.
     */
    public static function email(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }

        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return substr($email, 0, 1).'***'.substr($email, $at);
    }

    /**
     * "SK3112000000198742637541" -> "SK31***7541" - country + bank prefix and
     * the last four characters stay for matching against statements.
     */
    public static function iban(?string $iban): string
    {
        if ($iban === null || $iban === '') {
            return '';
        }
        $normalized = preg_replace('/\s+/', '', $iban) ?? $iban;
        if (strlen($normalized) < 8) {
            return '***';
        }

        return substr($normalized, 0, 4).'***'.substr($normalized, -4);
    }
}
