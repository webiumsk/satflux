<?php

namespace App\Support\Spreadsheet;

/**
 * CSV formula-injection guard. Exported values often come from buyers
 * (invoice metadata, order ids, emails): a leading =, +, -, @, tab or CR
 * would run as a formula when the file is opened in a spreadsheet, so such
 * text gets a leading apostrophe. Plain numbers (incl. negative amounts)
 * stay numeric. XLSX files are covered by NoFormulaValueBinder instead.
 */
final class SpreadsheetCell
{
    public static function neutralize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function csvRow(array $row): array
    {
        return array_map(self::neutralize(...), $row);
    }
}
