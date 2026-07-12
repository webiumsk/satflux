<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Hourly error/critical log counters (P1 phase 8). Counts ONLY - never the
 * message content. Incremented by a MessageLogged listener; read by the
 * health checks and the admin dashboard.
 */
class ErrorRateCounter
{
    protected const KEY_PREFIX = 'satflux.error_rate.';

    protected const COUNTED_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    public static function shouldCount(string $level): bool
    {
        return in_array(strtolower($level), self::COUNTED_LEVELS, true);
    }

    public static function increment(?\DateTimeInterface $at = null): void
    {
        try {
            $key = self::bucketKey($at);
            // Two-hour TTL keeps the previous bucket readable for the dashboard.
            Cache::add($key, 0, now()->addHours(2));
            Cache::increment($key);
        } catch (\Throwable) {
            // Counting must never break the code path that logged the error.
        }
    }

    /** Errors counted in the current hour bucket. */
    public static function currentHourCount(?\DateTimeInterface $at = null): int
    {
        try {
            return (int) Cache::get(self::bucketKey($at), 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Errors counted in the previous hour bucket (dashboard context). */
    public static function previousHourCount(): int
    {
        return self::currentHourCount(now()->subHour());
    }

    protected static function bucketKey(?\DateTimeInterface $at = null): string
    {
        return self::KEY_PREFIX.\Illuminate\Support\Carbon::parse($at ?? now())->format('Y-m-d-H');
    }
}
