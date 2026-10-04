<?php

namespace App\Services\Invoicing;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Formats document numbers from patterns (Y/R/M/N/C runs + literal text).
 *
 * Y = year digits (preferred), R = legacy year
 * M = month digits
 * N = zero-padded counter (preferred, run of 2+), C = legacy counter
 * Single Y or N are literal characters (e.g. expense prefix N in NYYYYNNNN).
 *
 * Example: DELYYYYNNNN → DEL20260001, INVYYYYNNNN → INV20260066
 */
final class DocumentNumberFormatter
{
    public function validateFormat(string $format): void
    {
        $format = strtoupper(trim($format));
        if ($format === '' || ! preg_match('/^[A-Z0-9]+$/', $format)) {
            throw new InvalidArgumentException('Format may only contain letters A-Z and digits.');
        }
        if (! str_contains($format, 'C') && ! preg_match('/N{2,}/', $format)) {
            throw new InvalidArgumentException('Format must include at least one counter (N or C).');
        }
    }

    public function format(string $pattern, int $counter, ?CarbonInterface $date = null): string
    {
        $this->validateFormat($pattern);
        $pattern = strtoupper($pattern);
        $date = $date ?? now();

        $result = '';
        $length = strlen($pattern);
        $index = 0;

        while ($index < $length) {
            $char = $pattern[$index];
            $runChar = $char;
            $runLen = 0;
            while ($index < $length && $pattern[$index] === $runChar) {
                $runLen++;
                $index++;
            }

            if ($char === 'M') {
                $result .= $this->padComponent((string) $date->month, $runLen);
            } elseif ($char === 'R' || ($char === 'Y' && $runLen >= 2)) {
                $result .= $this->padComponent((string) $date->year, $runLen);
            } elseif ($char === 'C' || ($char === 'N' && $runLen >= 2)) {
                $result .= str_pad((string) max(0, $counter), $runLen, '0', STR_PAD_LEFT);
            } else {
                $result .= str_repeat($char, $runLen);
            }
        }

        return $result;
    }

    /**
     * Parses a number produced by this format back into its parts, or null
     * when the number does not match the format at all (foreign/imported
     * numbers, numbers of a previous format). Matching the WHOLE pattern -
     * not just the trailing digits - keeps a year that sits after the
     * counter (FNNNNYYYY), a widened counter run or an overflowing counter
     * from being misread as the counter.
     *
     * @return array{counter: int, year: ?string, month: ?string}|null
     */
    public function parse(string $pattern, string $number): ?array
    {
        $pattern = strtoupper(trim($pattern));
        if ($pattern === '') {
            return null;
        }

        $regex = '';
        $groups = [];
        $length = strlen($pattern);
        $index = 0;
        while ($index < $length) {
            $char = $pattern[$index];
            $runLen = 0;
            while ($index < $length && $pattern[$index] === $char) {
                $runLen++;
                $index++;
            }

            if ($char === 'M') {
                $regex .= '(\d{'.$runLen.'})';
                $groups[] = 'month';
            } elseif ($char === 'R' || ($char === 'Y' && $runLen >= 2)) {
                $regex .= '(\d{'.$runLen.'})';
                $groups[] = 'year';
            } elseif ($char === 'C' || ($char === 'N' && $runLen >= 2)) {
                // str_pad never truncates - an overflowing counter is longer.
                $regex .= '(\d{'.$runLen.',})';
                $groups[] = 'counter';
            } else {
                $regex .= preg_quote(str_repeat($char, $runLen), '/');
            }
        }

        if (! in_array('counter', $groups, true)
            || ! preg_match('/^'.$regex.'$/i', trim($number), $matches)) {
            return null;
        }

        $parts = ['counter' => null, 'year' => null, 'month' => null];
        foreach ($groups as $position => $name) {
            // A repeated token must agree with itself (e.g. YYYY-NN-YYYY).
            $value = $matches[$position + 1];
            if ($parts[$name] !== null && $parts[$name] !== $value) {
                return null;
            }
            $parts[$name] = $value;
        }

        return [
            'counter' => (int) $parts['counter'],
            'year' => $parts['year'],
            'month' => $parts['month'],
        ];
    }

    /**
     * Counter of $number when it belongs to the period of $date under the
     * given reset rule, otherwise null. A yearly series must never count a
     * previous year's numbers (the "first invoice of 2027 is 0343" bug).
     * Formats without the period's date token cannot be told apart by the
     * number - $documentDate (issue date) decides then, when known.
     */
    public function counterInPeriod(
        string $pattern,
        string $number,
        string $resetPeriod,
        CarbonInterface $date,
        ?CarbonInterface $documentDate = null,
    ): ?int {
        $parsed = $this->parse($pattern, $number);
        if ($parsed === null) {
            return null;
        }
        if ($resetPeriod === 'never') {
            return $parsed['counter'];
        }

        $yearKnown = $parsed['year'] !== null;
        if ($yearKnown && $parsed['year'] !== $this->padComponent((string) $date->year, strlen($parsed['year']))) {
            return null;
        }

        $monthKnown = $parsed['month'] !== null;
        if ($resetPeriod === 'monthly' && $monthKnown
            && $parsed['month'] !== $this->padComponent((string) $date->month, strlen($parsed['month']))) {
            return null;
        }

        $periodFromNumber = $resetPeriod === 'monthly' ? ($yearKnown && $monthKnown) : $yearKnown;
        if (! $periodFromNumber && $documentDate !== null) {
            $sameYear = $documentDate->year === $date->year;
            $samePeriod = $resetPeriod === 'monthly'
                ? $sameYear && $documentDate->month === $date->month
                : $sameYear;
            if (! $samePeriod) {
                return null;
            }
        }

        return $parsed['counter'];
    }

    protected function padComponent(string $value, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        return substr(str_pad($value, $length, '0', STR_PAD_LEFT), -$length);
    }
}
