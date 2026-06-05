<?php

namespace App\Services\Invoicing;

use App\Models\Company;
use App\Support\Invoicing\CompanyContactImportFields;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyContactImportService
{
    private const MAX_ROWS = 5000;

    /** @deprecated Use CompanyContactImportFields::EXAMPLE_HEADERS */
    public const EXAMPLE_HEADERS = CompanyContactImportFields::EXAMPLE_HEADERS;

    /**
     * @return array{headers: list<string>, suggested_mapping: array<string, int|null>, row_count: int, preview: list<array<string, mixed>>}
     */
    public function preview(Company $company, UploadedFile $file, ?array $mapping = null): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping ?? $this->suggestMapping($parsed['headers']));

        $preview = [];
        foreach (array_slice($parsed['rows'], 0, 10) as $index => $row) {
            try {
                $payload = $this->rowToPayload($row, $mapping);
                if ($payload === null) {
                    $preview[] = ['row' => $index + 2, 'error' => 'Client name is required.'];
                } else {
                    $preview[] = array_merge(['row' => $index + 2], $this->payloadToPreview($payload));
                }
            } catch (\InvalidArgumentException $e) {
                $preview[] = ['row' => $index + 2, 'error' => $e->getMessage()];
            }
        }

        return [
            'headers' => $parsed['headers'],
            'suggested_mapping' => $mapping,
            'row_count' => count($parsed['rows']),
            'preview' => $preview,
        ];
    }

    /**
     * @param  array<string, int|string|null>|null  $mapping
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function importFile(Company $company, UploadedFile $file, ?array $mapping = null): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping ?? $this->suggestMapping($parsed['headers']));

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        foreach ($parsed['rows'] as $row) {
            $rowNumber++;

            if ($imported + $skipped >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Row limit exceeded ('.self::MAX_ROWS.').',
                ];
                break;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                $payload = $this->rowToPayload($row, $mapping);
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];

                continue;
            }

            if (! $payload) {
                $skipped++;
                $errors[] = ['row' => $rowNumber, 'message' => 'Client name is required.'];

                continue;
            }

            DB::transaction(function () use ($company, $payload) {
                $company->contacts()->create($payload);
            });

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function exampleDownloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([CompanyContactImportFields::EXAMPLE_HEADERS], null, 'A1');

        $sheet->fromArray([
            [
                'Vzorový klient s.r.o.',
                'Kvetná 1',
                '123 45',
                'Bratislava',
                'SK',
                '12345678',
                '2123456789',
                'SK2123456789',
                'vzory@superfaktura.sk',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '14',
                '',
                'EUR',
                '',
                '',
            ],
            [
                'Company Example',
                'Street 123',
                '08001',
                'Brno',
                'CZ',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '30',
                '',
                'EUR',
                '',
                '',
            ],
        ], null, 'A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'client_import_example.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    protected function parseSpreadsheet(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path) {
            throw new \InvalidArgumentException('Could not read uploaded file.');
        }

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($rows) < 2) {
            throw new \InvalidArgumentException('The spreadsheet has no data rows.');
        }

        $headerRow = array_shift($rows);
        $headers = array_map(fn ($h) => trim((string) ($h ?? '')), $headerRow);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int|null>
     */
    public function suggestMapping(array $headers): array
    {
        $aliases = CompanyContactImportFields::headerAliases();
        $mapping = [];

        foreach (CompanyContactImportFields::definitions() as $def) {
            $mapping[$def['key']] = null;
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $normalized = CompanyContactImportFields::normalizeHeader($header);
            $field = $aliases[$normalized] ?? null;
            if ($field !== null && $mapping[$field] === null) {
                $mapping[$field] = $index;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array<string, int|null>
     */
    protected function normalizeMapping(array $mapping): array
    {
        $normalized = [];
        foreach (CompanyContactImportFields::definitions() as $def) {
            $value = $mapping[$def['key']] ?? null;
            if ($value === '' || $value === null) {
                $normalized[$def['key']] = null;

                continue;
            }
            $normalized[$def['key']] = (int) $value;
        }

        foreach (CompanyContactImportFields::definitions() as $def) {
            if ($def['required'] && $normalized[$def['key']] === null) {
                throw new \InvalidArgumentException('Missing required column mapping: '.$def['key']);
            }
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @return array<string, mixed>|null
     */
    protected function rowToPayload(array $row, array $mapping): ?array
    {
        $data = [
            'name' => null,
            'registration_number' => null,
            'email' => null,
            'phone' => null,
            'fax' => null,
            'tax_id' => null,
            'vat_id' => null,
            'street' => null,
            'city' => null,
            'postal_code' => null,
            'country' => null,
            'iban' => null,
            'swift' => null,
            'delivery_street' => null,
            'delivery_postal_code' => null,
            'delivery_city' => null,
            'delivery_country' => null,
            'default_payment_terms_days' => 14,
            'notes' => null,
            'is_active' => true,
        ];

        $postalName = null;
        $web = null;

        foreach ($mapping as $field => $index) {
            if ($index === null) {
                continue;
            }

            $value = $this->cellString($row[$index] ?? null);
            if ($value === null) {
                continue;
            }

            if ($field === 'delivery_name') {
                $postalName = $value;

                continue;
            }

            if ($field === 'web') {
                $web = $value;

                continue;
            }

            if ($field === 'country' || $field === 'delivery_country') {
                $data[$field] = $this->expandCountry($value);

                continue;
            }

            if ($field === 'default_payment_terms_days') {
                if (! is_numeric($value)) {
                    throw new \InvalidArgumentException('Invalid payment terms (days).');
                }
                $data[$field] = (int) $value;

                continue;
            }

            if ($field === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid e-mail address.');
            }

            if (array_key_exists($field, $data)) {
                $data[$field] = $value;
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if ($web) {
            $notes = trim($notes."\nWeb: ".$web);
        }
        if ($postalName && ! $data['delivery_street']) {
            $notes = trim($notes."\nPoštová adresa: ".$postalName);
        }
        $data['notes'] = $notes !== '' ? $notes : null;
        $data['name'] = $name;

        return array_filter(
            $data,
            fn ($value, $key) => $key === 'is_active' || $key === 'default_payment_terms_days' || ($value !== null && $value !== ''),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function payloadToPreview(array $payload): array
    {
        return [
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'city' => $payload['city'] ?? null,
            'country' => $payload['country'] ?? null,
            'registration_number' => $payload['registration_number'] ?? null,
        ];
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->cellString($cell) !== null) {
                return false;
            }
        }

        return true;
    }

    protected function cellString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        } elseif (is_int($value) || is_float($value)) {
            $value = trim((string) $value);
        } else {
            return null;
        }

        return $value === '' ? null : $value;
    }

    protected function expandCountry(string $value): string
    {
        $code = strtoupper(trim($value));

        return match ($code) {
            'SK' => 'Slovensko',
            'CZ' => 'Česko',
            'AT' => 'Rakúsko',
            'HU' => 'Maďarsko',
            'PL' => 'Poľsko',
            'DE' => 'Nemecko',
            'US', 'USA' => 'US',
            default => $value,
        };
    }
}
