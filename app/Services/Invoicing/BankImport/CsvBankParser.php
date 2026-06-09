<?php

namespace App\Services\Invoicing\BankImport;

use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CsvBankParser implements BankStatementParser
{
    public function supports(string $filename, string $contents): bool
    {
        $lower = strtolower($filename);

        return str_ends_with($lower, '.csv') || str_ends_with($lower, '.txt');
    }

    public function parse(string $contents): array
    {
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $converted = @iconv('ISO-8859-2', 'UTF-8//IGNORE', $contents);
            if ($converted !== false) {
                $contents = $converted;
            }
        }

        $delimiter = $this->detectDelimiter($contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if ($lines === []) {
            throw ValidationException::withMessages(['file' => ['CSV file is empty.']]);
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv((string) $headerLine, $delimiter);
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headers);

        $profile = $this->resolveProfile($headers);
        $map = $this->columnMap($headers, $profile);

        $parsed = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            $row = $this->parseRow($cols, $map);
            if ($row !== null) {
                $parsed[] = $row;
            }
        }

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'file' => ['No transactions parsed from CSV. Check column headers.'],
            ]);
        }

        return $parsed;
    }

    protected function detectDelimiter(string $contents): string
    {
        $first = strtok($contents, "\n") ?: '';

        $semicolon = substr_count($first, ';');
        $comma = substr_count($first, ',');

        return $semicolon >= $comma ? ';' : ',';
    }

    protected function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));

        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h) ?: $h;
    }

    /**
     * @param  list<string>  $headers
     */
    protected function resolveProfile(array $headers): string
    {
        $joined = implode(' ', $headers);
        if (str_contains($joined, 'zauctovania') || str_contains($joined, 'protistrany')) {
            return 'tatra';
        }

        return (string) config('bank_import.default_csv_profile', 'generic');
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    protected function columnMap(array $headers, string $profile): array
    {
        $profiles = config('bank_import.csv_profiles', []);
        $needles = $profiles[$profile] ?? $profiles['generic'] ?? [];
        $map = [];

        foreach ($needles as $field => $candidates) {
            foreach ($headers as $index => $header) {
                foreach ($candidates as $candidate) {
                    $c = $this->normalizeHeader($candidate);
                    if ($header === $c || str_contains($header, $c)) {
                        $map[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        if (! isset($map['date'], $map['amount'])) {
            throw ValidationException::withMessages([
                'file' => ['CSV must include date and amount columns.'],
            ]);
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $cols
     * @param  array<string, int>  $map
     */
    protected function parseRow(array $cols, array $map): ?ParsedBankTransaction
    {
        $amountRaw = $this->col($cols, $map, 'amount');
        if ($amountRaw === null || $amountRaw === '') {
            return null;
        }

        $amount = $this->parseAmount($amountRaw);
        if ($amount == 0.0) {
            return null;
        }

        $directionRaw = $this->col($cols, $map, 'direction');
        $counterparty = $this->col($cols, $map, 'counterparty');
        $reference = $this->col($cols, $map, 'reference');
        $direction = app(BankTransactionDirectionGuesser::class)->fromAmountAndHints(
            $amount,
            $directionRaw,
            $counterparty,
            $reference,
        );

        $dateRaw = $this->col($cols, $map, 'date');
        if ($dateRaw === null || $dateRaw === '') {
            return null;
        }

        $currency = strtoupper($this->col($cols, $map, 'currency') ?: 'EUR');

        return new ParsedBankTransaction(
            bookedAt: $this->parseDate($dateRaw),
            amount: abs($amount),
            currency: $currency,
            direction: $direction,
            variableSymbol: BankSymbolNormalizer::variableSymbol($this->col($cols, $map, 'variable_symbol')),
            constantSymbol: BankSymbolNormalizer::constantSymbol($this->col($cols, $map, 'constant_symbol')),
            specificSymbol: BankSymbolNormalizer::specificSymbol($this->col($cols, $map, 'specific_symbol')),
            counterpartyName: $counterparty,
            reference: $reference,
        );
    }

    /**
     * @param  list<string|null>  $cols
     * @param  array<string, int>  $map
     */
    protected function col(array $cols, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }
        $value = $cols[$map[$field]] ?? null;

        return $value !== null ? trim((string) $value) : null;
    }

    protected function parseAmount(string $raw): float
    {
        $normalized = str_replace([' ', "\xc2\xa0"], '', $raw);
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $normalized) ?? '');

        return (float) $normalized;
    }

    protected function parseDate(string $raw): \DateTimeInterface
    {
        $raw = trim($raw);
        $formats = ['d.m.Y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $dt = Carbon::createFromFormat('!'.$format, $raw);
            if ($dt instanceof Carbon) {
                return $dt;
            }
        }

        return Carbon::parse($raw);
    }
}
