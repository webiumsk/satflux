<?php

namespace App\Services\Invoicing;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Formats document numbers from patterns (R/M/C runs + literal text).
 *
 * R = year digits, M = month digits, C = zero-padded counter.
 * Example: DELRRRRCCCC → DEL20260001, INVRRRRCCCC → INV20260066, PORRRRRCCCC → PO20260001
 */
final class DocumentNumberFormatter
{
    public function validateFormat(string $format): void
    {
        $format = strtoupper(trim($format));
        if ($format === '' || ! preg_match('/^[A-Z0-9]+$/', $format)) {
            throw new InvalidArgumentException('Format may only contain letters A-Z and digits.');
        }
        if (! str_contains($format, 'C')) {
            throw new InvalidArgumentException('Format must include at least one counter (C).');
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
            if (in_array($char, ['R', 'M', 'C'], true)) {
                $runChar = $char;
                $runLen = 0;
                while ($index < $length && $pattern[$index] === $runChar) {
                    $runLen++;
                    $index++;
                }

                $result .= match ($runChar) {
                    'R' => $this->padComponent((string) $date->year, $runLen),
                    'M' => $this->padComponent((string) $date->month, $runLen),
                    'C' => str_pad((string) max(0, $counter), $runLen, '0', STR_PAD_LEFT),
                };
            } else {
                $result .= $char;
                $index++;
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
