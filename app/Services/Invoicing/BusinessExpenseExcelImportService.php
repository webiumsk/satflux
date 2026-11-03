<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Support\Invoicing\BusinessExpenseImportFields;
use App\Support\Invoicing\ImportDateParser;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessExpenseExcelImportService
{
    private const MAX_ROWS = 2000;

    public function __construct(
        protected DocumentSequenceService $sequenceService,
    ) {}

    /**
     * @return array{headers: list<string>, suggested_mapping: array<string, int|null>, row_count: int, preview: list<array<string, mixed>>}
     */
    public function preview(Company $company, UploadedFile $file, ?array $mapping = null): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping ?? $this->suggestMapping($parsed['headers']), count($parsed['headers']));

        $preview = [];
        foreach (array_slice($parsed['rows'], 0, 10) as $index => $row) {
            try {
                $preview[] = array_merge(
                    ['row' => $index + 2],
                    $this->rowToExpensePreview($company, $row, $mapping)
                );
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
     * @param  array<string, int|string|null>  $mapping
     * @return array{imported: int, skipped: int, errors: list<array{row: int, internal_number: string|null, message: string}>}
     */
    public function import(Company $company, UploadedFile $file, array $mapping): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping, count($parsed['headers']));
        $reservedInternalNumbers = [];
        foreach ($parsed['rows'] as $row) {
            $number = $this->internalNumberFromRow($row, $mapping);
            if ($number !== null) {
                $reservedInternalNumbers[$number] = true;
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        foreach ($parsed['rows'] as $row) {
            $rowNumber++;

            if ($imported + $skipped >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $rowNumber,
                    'internal_number' => $this->internalNumberFromRow($row, $mapping),
                    'message' => 'Row limit exceeded.',
                ];
                break;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                DB::transaction(function () use ($company, $row, $mapping, $reservedInternalNumbers) {
                    $this->importRow($company, $row, $mapping, $reservedInternalNumbers);
                });
                $imported++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'internal_number' => $this->internalNumberFromRow($row, $mapping),
                    'message' => $e->getMessage(),
                ];
            }
        }

        AuditLog::log('business_expense.imported', 'company', $company->id, [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

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
        $sheet->fromArray([BusinessExpenseImportFields::EXAMPLE_HEADERS], null, 'A1');
        $sheet->fromArray([
            [
                'Monacor káble',
                'Hifiaudio',
                'MONACOR',
                '250351',
                '2026001',
                '2026-01-15',
                '2026-01-29',
                '2026-01-15',
                '45.76',
                'EUR',
                '2026-01-20',
                '250351',
                '',
                '',
                'import',
                '',
                '12345678',
                '2123456789',
                'info@monacor.sk',
                'Jegorovova 5',
                'Banská Bystrica',
                'SK',
            ],
            [
                'UPS preprava',
                'Doprava',
                'Slovak Parcel Service',
                '1270000001',
                '2026002',
                '2026-02-01',
                '2026-02-15',
                '2026-02-01',
                '106.15',
                'EUR',
                '',
                '1270000001',
                '',
                '',
                '',
                'Dobierka',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ], null, 'A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'expense_import_example.xlsx', [
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

        $reader = IOFactory::createReaderForFile($path);
        if ($reader instanceof Csv) {
            // CSV identifiers and formula-looking values are text, without
            // changing the application's global binder or Excel cell types.
            $reader->setValueBinder(new StringValueBinder);
        }
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        if ($sheet->getHighestDataRow() > self::MAX_ROWS + 1) {
            throw new \InvalidArgumentException('Row limit exceeded.');
        }
        if (Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 256) {
            throw new \InvalidArgumentException('Column limit exceeded.');
        }
        // Read numbers independently of currency/thousands display formats and
        // never calculate uploaded formulas. Plain zero masks still preserve
        // leading zeros in whole-number identifiers without rounding amounts.
        $rows = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow(), null, false, false, false);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if (! is_int($value) && ! is_float($value)) {
                    continue;
                }
                $cell = $sheet->getCell([$columnIndex + 1, $rowIndex + 1]);
                if (is_finite((float) $value)
                    && floor((float) $value) === (float) $value
                    && preg_match('/^0{2,}$/', $cell->getStyle()->getNumberFormat()->getFormatCode())) {
                    $rows[$rowIndex][$columnIndex] = $cell->getFormattedValue();
                }
            }
        }

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
        $aliases = BusinessExpenseImportFields::headerAliases();
        $mapping = [];

        foreach (BusinessExpenseImportFields::definitions() as $def) {
            $mapping[$def['key']] = null;
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $normalized = BusinessExpenseImportFields::normalizeHeader($header);
            $field = $aliases[$normalized] ?? null;
            if ($field !== null && $mapping[$field] === null) {
                $mapping[$field] = $index;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, int|null>
     */
    protected function normalizeMapping(array $mapping, int $columnCount): array
    {
        $normalized = [];
        foreach (BusinessExpenseImportFields::definitions() as $def) {
            $value = $mapping[$def['key']] ?? null;
            if ($value === '' || $value === null) {
                $normalized[$def['key']] = null;

                continue;
            }
            if (
                (! is_int($value) && (! is_string($value) || ! preg_match('/^\d+$/', $value)))
                || (int) $value < 0
                || (int) $value >= $columnCount
            ) {
                throw new \InvalidArgumentException('Invalid column mapping: '.$def['key']);
            }
            $normalized[$def['key']] = (int) $value;
        }

        foreach (BusinessExpenseImportFields::definitions() as $def) {
            if ($def['required'] && $normalized[$def['key']] === null) {
                throw new \InvalidArgumentException('Missing required column mapping: '.$def['key']);
            }
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @param  array<string|int, true>  $reservedInternalNumbers
     * @return array<string, mixed>
     */
    protected function rowToExpenseData(Company $company, array $row, array $mapping, bool $validateOnly = false, array $reservedInternalNumbers = []): array
    {
        $issueDate = $this->parseDate($this->cell($row, $mapping['issue_date']));
        $total = $this->parseAmount($this->cell($row, $mapping['total']));

        if (! $issueDate) {
            throw new \InvalidArgumentException('Issue date is invalid or missing.');
        }
        if ($total === null || $total < 0) {
            throw new \InvalidArgumentException('Total amount is invalid or missing.');
        }

        $dueDate = $this->optionalDate($row, $mapping, 'due_date');
        $deliveryDate = $this->optionalDate($row, $mapping, 'delivery_date') ?? $issueDate;
        $paidAt = $this->optionalDate($row, $mapping, 'paid_at');
        $currency = strtoupper($this->cell($row, $mapping['currency']) ?? $company->default_currency ?? 'EUR');
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency is invalid.');
        }

        $paymentAmountRaw = $this->cell($row, $mapping['payment_amount']);
        $paymentAmount = $this->parseAmount($paymentAmountRaw);
        if ($paymentAmountRaw !== null && $paymentAmount === null) {
            throw new \InvalidArgumentException('Payment amount is invalid.');
        }
        $paymentCurrency = strtoupper($this->cell($row, $mapping['payment_currency']) ?? $currency);
        $paid = $paidAt !== null && (
            $paymentAmountRaw === null
            || ($paymentAmount >= $total && $paymentCurrency === $currency)
        );

        $internalNumber = $this->cell($row, $mapping['internal_number']);
        if ($internalNumber === null || $internalNumber === '') {
            if ($validateOnly) {
                $internalNumber = '(auto)';
            } else {
                $attempts = 0;
                do {
                    if ($attempts++ > self::MAX_ROWS) {
                        throw new \InvalidArgumentException('No free internal number is available.');
                    }
                    $internalNumber = $this->sequenceService->nextNumber($company, 'expense');
                } while (isset($reservedInternalNumbers[$internalNumber]));
            }
        }

        if (
            ! $validateOnly
            && BusinessExpense::query()
                ->where('company_id', $company->id)
                ->where('internal_number', $internalNumber)
                ->exists()
        ) {
            throw new \InvalidArgumentException('Internal number already exists: '.$internalNumber);
        }

        $title = $this->resolveTitle($row, $mapping);
        $note = $this->buildInternalNote($row, $mapping, $paid ? null : $paidAt);
        $data = [
            'internal_number' => $internalNumber,
            'external_number' => $this->cell($row, $mapping['external_number']),
            'title' => $title,
            'variable_symbol' => $this->cell($row, $mapping['variable_symbol'])
                ?? $this->cell($row, $mapping['external_number']),
            'constant_symbol' => $this->cell($row, $mapping['constant_symbol']),
            'specific_symbol' => $this->cell($row, $mapping['specific_symbol']),
            'issue_date' => $issueDate,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'total' => $total,
            'currency' => $currency,
            'internal_note' => $note,
            'paid' => $paid,
            'paid_at' => $paidAt,
        ];

        foreach (['internal_number' => 64, 'external_number' => 64, 'title' => 255, 'variable_symbol' => 32, 'constant_symbol' => 16, 'specific_symbol' => 16, 'internal_note' => 4000] as $field => $limit) {
            if (mb_strlen((string) $data[$field]) > $limit) {
                throw new \InvalidArgumentException('Field exceeds maximum length: '.$field);
            }
        }

        return $data;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @return array<string, mixed>
     */
    protected function rowToExpensePreview(Company $company, array $row, array $mapping): array
    {
        $data = $this->rowToExpenseData($company, $row, $mapping, validateOnly: true);

        return [
            'internal_number' => $data['internal_number'],
            'external_number' => $data['external_number'],
            'title' => $data['title'],
            'issue_date' => $data['issue_date']->toDateString(),
            'due_date' => $data['due_date']?->toDateString(),
            'total' => number_format($data['total'], 2, '.', ''),
            'currency' => $data['currency'],
            'paid' => $data['paid'],
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @param  array<string|int, true>  $reservedInternalNumbers
     */
    protected function importRow(Company $company, array $row, array $mapping, array $reservedInternalNumbers = []): void
    {
        $data = $this->rowToExpenseData($company, $row, $mapping, reservedInternalNumbers: $reservedInternalNumbers);

        BusinessExpense::create([
            'company_id' => $company->id,
            'status' => $data['paid'] ? BusinessExpenseStatus::Paid : BusinessExpenseStatus::Recorded,
            'internal_number' => $data['internal_number'],
            'external_number' => $data['external_number'],
            'title' => $data['title'],
            'variable_symbol' => $data['variable_symbol'],
            'constant_symbol' => $data['constant_symbol'],
            'specific_symbol' => $data['specific_symbol'],
            'issue_date' => $data['issue_date'],
            'delivery_date' => $data['delivery_date'],
            'due_date' => $data['due_date'],
            'total' => $data['total'],
            'currency' => $data['currency'],
            'internal_note' => $data['internal_note'],
            'paid_at' => $data['paid'] ? $data['paid_at']?->startOfDay() : null,
        ]);
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    protected function resolveTitle(array $row, array $mapping): ?string
    {
        $title = $this->cell($row, $mapping['title']);
        if ($title) {
            return $title;
        }

        $supplier = $this->cell($row, $mapping['supplier_name']);
        $category = $this->cell($row, $mapping['category']);

        if ($supplier && $category) {
            return $supplier.' - '.$category;
        }

        return $supplier ?? $category;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    protected function buildInternalNote(array $row, array $mapping, ?Carbon $unsettledPaymentDate = null): ?string
    {
        $parts = [];
        $userNote = $this->cell($row, $mapping['internal_note']);
        if ($userNote !== null) {
            $parts[] = $userNote;
        }

        $labels = [
            'tags' => 'Tags',
            'supplier_name' => 'Supplier',
            'category' => 'Category',
            'supplier_registration_number' => 'Supplier registration number',
            'supplier_tax_id' => 'Supplier tax ID',
            'supplier_vat_id' => 'Supplier VAT ID',
            'supplier_phone' => 'Supplier phone',
            'supplier_email' => 'Supplier email',
            'supplier_street' => 'Supplier street',
            'supplier_postal_code' => 'Supplier postal code',
            'supplier_city' => 'Supplier city',
            'supplier_country' => 'Supplier country',
            'supplier_bank_account' => 'Supplier bank account',
            'payment_method' => 'Payment method',
            'payment_amount' => 'Payment amount',
            'payment_currency' => 'Payment currency',
            'payment_exchange_rate' => 'Payment exchange rate',
            'item_quantity' => 'Item quantity',
            'item_units' => 'Item units',
            'items' => 'Items',
        ];
        foreach ($labels as $field => $label) {
            $value = $this->cell($row, $mapping[$field]);
            if ($value !== null) {
                $parts[] = $label.': '.$value;
            }
        }
        if ($unsettledPaymentDate !== null) {
            $parts[] = 'Payment date: '.$unsettledPaymentDate->toDateString();
        }

        $parts[] = 'import';

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    protected function internalNumberFromRow(array $row, array $mapping): ?string
    {
        return $this->cell($row, $mapping['internal_number'] ?? null);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function cell(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = $row[$index] ?? null;
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (abs($value - (int) $value) < 0.00001) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        return null;
    }

    protected function parseDate(?string $value): ?Carbon
    {
        return (new ImportDateParser('dmy_dot'))->parse($value);
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    protected function optionalDate(array $row, array $mapping, string $field): ?Carbon
    {
        $value = $this->cell($row, $mapping[$field]);
        $date = $this->parseDate($value);
        if ($value !== null && $date === null) {
            throw new \InvalidArgumentException('Date is invalid: '.$field);
        }

        return $date;
    }

    protected function parseAmount(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', '', $value) ?? '';
        $normalized = str_replace(',', '.', $normalized);
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $normalized)) {
            return null;
        }

        $amount = (float) $normalized;
        if (! is_finite($amount) || $amount < 0 || $amount > 999999999999.99) {
            return null;
        }

        return round($amount, 2);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null) {
                continue;
            }
            if (is_string($cell) && trim($cell) === '') {
                continue;
            }

            return false;
        }

        return true;
    }
}
