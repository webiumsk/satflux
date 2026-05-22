<?php

namespace App\Support;

class RaffleBuyerPrivacy
{
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false || $at < 1 || $at >= strlen($email) - 1) {
            return '***';
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $visible = strlen($local) <= 1 ? $local : substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    public static function maskTicketsBuyerEmails(array $tickets): array
    {
        return array_map(static function (array $ticket): array {
            if (array_key_exists('buyerEmail', $ticket)) {
                $ticket['buyerEmail'] = self::maskEmail(
                    is_string($ticket['buyerEmail'] ?? null) ? $ticket['buyerEmail'] : null
                );
            }

            return $ticket;
        }, $tickets);
    }

    /**
     * @param  list<array<string, mixed>>  $drawings
     * @return list<array<string, mixed>>
     */
    public static function maskDrawingsWinnerEmails(array $drawings): array
    {
        return array_map(static function (array $drawing): array {
            if (array_key_exists('winnerEmail', $drawing)) {
                $drawing['winnerEmail'] = self::maskEmail(
                    is_string($drawing['winnerEmail'] ?? null) ? $drawing['winnerEmail'] : null
                );
            }

            return $drawing;
        }, $drawings);
    }
}
