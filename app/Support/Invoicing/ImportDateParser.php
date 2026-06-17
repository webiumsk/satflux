<?php

namespace App\Support\Invoicing;

use Carbon\Carbon;

final class ImportDateParser
{
    private const EXCEL_SERIAL_MIN = 25569;

    private const EXCEL_SERIAL_MAX = 60000;

    public function __construct(
        private readonly string $format = 'auto',
    ) {}

    public function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = $this->normalizeDateInput(trim($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $this->safeDate(
                (int) substr($value, 0, 4),
                (int) substr($value, 5, 2),
                (int) substr($value, 8, 2),
            );
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            $serial = (int) floor((float) $value);
            if ($serial >= self::EXCEL_SERIAL_MIN && $serial <= self::EXCEL_SERIAL_MAX) {
                return Carbon::createFromTimestampUTC(($serial - 25569) * 86400)->startOfDay();
            }
        }

        return match ($this->format) {
            'dmy_dot' => $this->parseDmyDot($value) ?? $this->parseSlashDate($value, 'dmy'),
            'ymd_dash' => $this->parseYmdDash($value),
            'mdy_slash' => $this->parseSlashDate($value, 'mdy'),
            default => $this->parseAuto($value),
        };
    }

    protected function parseAuto(string $value): ?Carbon
    {
        return $this->parseYmdDash($value)
            ?? $this->parseDmyDot($value)
            ?? $this->parseSlashDate($value, 'mdy')
            ?? $this->parseSlashDate($value, 'dmy');
    }

    protected function normalizeDateInput(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);

        return preg_replace('/\s+\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?$/', '', $value) ?? $value;
    }

    protected function parseDmyDot(string $value): ?Carbon
    {
        if (! preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $matches)) {
            return null;
        }

        return $this->safeDate(
            $this->expandTwoDigitYear((int) $matches[3]),
            (int) $matches[2],
            (int) $matches[1],
        );
    }

    protected function parseYmdDash(string $value): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
            return null;
        }

        return $this->safeDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    protected function parseSlashDate(string $value, string $order): ?Carbon
    {
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $value, $matches)) {
            return null;
        }

        $first = (int) $matches[1];
        $second = (int) $matches[2];
        $year = $this->expandTwoDigitYear((int) $matches[3]);

        if ($order === 'mdy') {
            return $this->safeDate($year, $first, $second);
        }

        return $this->safeDate($year, $second, $first);
    }

    protected function expandTwoDigitYear(int $year): int
    {
        if ($year >= 100) {
            return $year;
        }

        return $year >= 70 ? 1900 + $year : 2000 + $year;
    }

    protected function safeDate(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::createFromDate($year, $month, $day)->startOfDay();
    }
}
