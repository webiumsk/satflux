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

    protected function padComponent(string $value, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        return substr(str_pad($value, $length, '0', STR_PAD_LEFT), -$length);
    }
}
