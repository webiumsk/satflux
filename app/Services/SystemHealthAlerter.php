<?php

namespace App\Services;

use App\Mail\SystemHealthAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail alerting for health check transitions (P1 phase 8).
 *
 * A failing check alerts at most once per throttle window; a check that was
 * alerted on and turns healthy again produces one recovery notification.
 * Without a configured alert address everything is logged only.
 */
class SystemHealthAlerter
{
    protected const ALERTED_KEY_PREFIX = 'satflux.health_alerted.';

    protected const THROTTLE_KEY_PREFIX = 'satflux.health_alert_throttle.';

    /**
     * @param  array<string, array{ok: bool, detail: string, duration_ms: int}>  $results
     */
    public function handle(array $results): void
    {
        $failed = [];
        $alertable = [];
        $recovered = [];

        foreach ($results as $check => $result) {
            $alertedKey = self::ALERTED_KEY_PREFIX.$check;
            if (! $result['ok']) {
                $failed[] = $check;
                // Remember the failure without expiry - recovery clears it.
                Cache::forever($alertedKey, true);
                if (Cache::add(self::THROTTLE_KEY_PREFIX.$check, 1, now()->addMinutes($this->throttleMinutes()))) {
                    $alertable[] = $check;
                }
            } elseif (Cache::pull($alertedKey)) {
                $recovered[] = $check;
                Cache::forget(self::THROTTLE_KEY_PREFIX.$check);
            }
        }

        if ($alertable === [] && $recovered === []) {
            return;
        }

        $email = trim((string) config('monitoring.alert_email'));
        if ($email === '') {
            Log::warning('System health transition (no SYSTEM_ALERT_EMAIL configured)', [
                'failed' => $failed,
                'recovered' => $recovered,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new SystemHealthAlertMail($results, $failed, $recovered));
        } catch (\Throwable $e) {
            Log::error('System health alert e-mail failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function throttleMinutes(): int
    {
        return max(1, (int) config('monitoring.alert_throttle_minutes', 60));
    }
}
