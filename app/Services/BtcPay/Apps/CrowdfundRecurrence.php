<?php

namespace App\Services\BtcPay\Apps;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

/** Preserves the integer/UTC calendar contract used by BTCPay's Crowdfund reset. */
class CrowdfundRecurrence
{
    private const MAX_AMOUNT = 2147483647;

    private const MIN_TIMESTAMP = -62135596800;

    private const MAX_TIMESTAMP = 253402300799;

    /**
     * Validate a merged Crowdfund configuration and convert weekly intervals to days.
     * Configurations without recurrence fields preserve partial updates; explicit zero disables resets.
     *
     * @throws ValidationException When an interval or its next UTC reset exceeds the host's limits.
     */
    public function normalize(array $config): array
    {
        if (! array_key_exists('resetEvery', $config) && ! array_key_exists('resetEveryAmount', $config)) {
            return $config;
        }

        $period = $config['resetEvery'] ?? null;
        if ($period !== null && (! is_string($period) || ! in_array($period, ['Never', 'Hour', 'Day', 'Week', 'Month', 'Year'], true))) {
            $this->fail('resetEvery', 'in');
        }

        $value = $config['resetEveryAmount'] ?? null;
        if ($value === null) {
            if ($period !== 'Never') {
                $this->fail('resetEveryAmount', 'required');
            }
            $amount = 0;
        } else {
            if (! is_int($value) && (! is_string($value) || ! preg_match('/\A[0-9]+\z/D', $value))) {
                $this->fail('resetEveryAmount', 'integer');
            }
            if (is_int($value) && $value < 0) {
                $this->fail('resetEveryAmount', 'min.numeric', ['min' => 0]);
            }
            // Compare before casting so an oversized numeric string cannot overflow.
            $digits = ltrim((string) $value, '0');
            $maximum = (string) self::MAX_AMOUNT;
            if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
                $this->fail('resetEveryAmount', 'max.numeric', ['max' => self::MAX_AMOUNT]);
            }
            $amount = (int) $value;
        }

        if ($amount === 0 || $period === 'Never') {
            $config['resetEveryAmount'] = 0;
            $config['resetEvery'] = 'Never';

            return $config;
        }

        if ($period === null) {
            $this->fail('resetEvery', 'required');
        }
        $weekly = $period === 'Week';
        if ($weekly) {
            if ($amount > intdiv(self::MAX_AMOUNT, 7)) {
                $this->fail('resetEveryAmount', 'max.numeric', ['max' => intdiv(self::MAX_AMOUNT, 7)]);
            }
            $amount *= 7;
            $period = 'Day';
        }

        $start = $this->startDate($config['startDate'] ?? null);
        // These bounds match DateTime.AddHours/AddDays/AddMonths/AddYears, including
        // month-end clipping rather than PHP's overflowing month arithmetic.
        $maximum = match ($period) {
            'Hour' => intdiv(self::MAX_TIMESTAMP - $start->getTimestamp(), 3600),
            'Day' => intdiv(self::MAX_TIMESTAMP - $start->getTimestamp(), 86400),
            'Month' => (9999 - (int) $start->format('Y')) * 12 + 12 - (int) $start->format('n'),
            'Year' => 9999 - (int) $start->format('Y'),
        };
        if ($amount > $maximum) {
            $this->fail('resetEveryAmount', 'max.numeric', ['max' => $weekly ? intdiv($maximum, 7) : $maximum]);
        }

        $config['resetEveryAmount'] = $amount;
        $config['resetEvery'] = $period;
        $config['startDate'] = $start->getTimestamp();

        return $config;
    }

    /** Parse Unix seconds or a bounded ISO date within the host's UTC calendar. */
    private function startDate(mixed $value): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            $this->fail('startDate', 'required');
        }

        try {
            if (is_int($value) || (is_string($value) && preg_match('/\A-?[0-9]{1,12}\z/D', $value))) {
                $timestamp = (int) $value;
                $date = new DateTimeImmutable('@'.$timestamp);
            } elseif (is_string($value) && strlen($value) <= 40
                && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}(?:[T ][0-9]{2}:[0-9]{2}(?::[0-9]{2}(?:\.[0-9]{1,7})?)?(?:Z|[+-][0-9]{2}:[0-9]{2})?)?\z/D', $value)) {
                // .NET ISO dates can have seven fractional digits; PHP accepts six.
                $value = preg_replace('/(\.[0-9]{6})[0-9]/', '$1', $value);
                $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    $this->fail('startDate', 'date');
                }
            } else {
                $this->fail('startDate', 'date');
            }
        } catch (\DateMalformedStringException) {
            $this->fail('startDate', 'date');
        }

        $timestamp = $date->getTimestamp();
        if ($timestamp < self::MIN_TIMESTAMP || $timestamp > self::MAX_TIMESTAMP) {
            $this->fail('startDate', 'date');
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function fail(string $field, string $rule, array $parameters = []): never
    {
        throw ValidationException::withMessages([
            'config.'.$field => __('validation.'.$rule, ['attribute' => 'config.'.$field] + $parameters),
        ]);
    }
}
