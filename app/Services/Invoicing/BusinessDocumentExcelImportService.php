<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\BusinessDocumentImportFields;
use App\Support\Invoicing\BuyerSnapshot;
use App\Support\Invoicing\ImportDateParser;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessDocumentExcelImportService
{
    private const MAX_ROWS = 2000;

    public function __construct(
        protected DocumentTotalsCalculator $totalsCalculator,
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    /**
     * @return array{headers: list<string>, suggested_mapping: array<string, int|null>, row_count: int, preview: list<array<string, mixed>>}
     */
    public function preview(Company $company, UploadedFile $file, ?array $mapping = null, array $options = []): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping ?? $this->suggestMapping($parsed['headers']));

        $preview = [];
        foreach (array_slice($parsed['rows'], 0, 10) as $index => $row) {
            try {
                $preview[] = array_merge(
                    ['row' => $index + 2],
                    $this->rowToInvoiceData($company, $row, $mapping, $options, validateOnly: true)
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
     * @param  array{line_name?: string, line_description?: string, create_contacts?: bool}  $options
     * @return array{imported: int, skipped: int, contacts_created: int, contacts_linked: int, errors: list<array{row: int, invoice_number: string|null, message: string}>}
     */
    public function import(Company $company, UploadedFile $file, array $mapping, array $options = []): array
    {
        $parsed = $this->parseSpreadsheet($file);
        $mapping = $this->normalizeMapping($mapping);

        $imported = 0;
        $skipped = 0;
        $contactsCreated = 0;
        $contactsLinked = 0;
        $errors = [];
        $rowNumber = 1;

        foreach ($parsed['rows'] as $row) {
            $rowNumber++;

            if ($imported + $skipped >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $rowNumber,
                    'invoice_number' => $this->invoiceNumberFromRow($row, $mapping),
                    'message' => 'Row limit exceeded.',
                ];
                break;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                $result = DB::transaction(function () use ($company, $row, $mapping, $options) {
                    return $this->importRow($company, $row, $mapping, $options);
                });
                $imported++;
                if ($result['contact_created']) {
                    $contactsCreated++;
                } elseif ($result['contact_linked']) {
                    $contactsLinked++;
                }
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'invoice_number' => $this->invoiceNumberFromRow($row, $mapping),
                    'message' => $e->getMessage(),
                ];
            }
        }

        AuditLog::log('business_document.imported', 'company', $company->id, [
            'imported' => $imported,
            'skipped' => $skipped,
            'contacts_created' => $contactsCreated,
            'contacts_linked' => $contactsLinked,
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'contacts_created' => $contactsCreated,
            'contacts_linked' => $contactsLinked,
            'errors' => $errors,
        ];
    }

    public function exampleDownloadResponse(): StreamedResponse
    {
        $headers = [
            'Č. faktúry', 'Variabilný symbol', 'Vytvorené', 'Dátum splatnosti',
            'IČO klienta', 'Názov / Meno', 'Adresa klienta', 'Mesto klienta', 'PSČ',
            'Krajina klienta (kód ISO)', 'E-mail', 'Suma', 'Fakturačná mena (kód ISO)', 'Dátum úhrady',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->fromArray([
            ['20240001', '20240001', '2024-01-15', '2024-01-29', '12345678', 'Vzorový klient s.r.o.', 'Hlavná 1', 'Bratislava', '81101', 'SK', 'klient@example.sk', '120.00', 'EUR', ''],
            ['20240002', '20240002', '2024-02-01', '2024-02-15', '', 'John Doe', 'Street 5', 'Prague', '11000', 'CZ', '', '250.50', 'EUR', '2024-02-10'],
        ], null, 'A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'invoice_import_example.xlsx', [
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
        $aliases = BusinessDocumentImportFields::headerAliases();
        $mapping = [];

        foreach (BusinessDocumentImportFields::definitions() as $def) {
            $mapping[$def['key']] = null;
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $normalized = BusinessDocumentImportFields::normalizeHeader($header);
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
        foreach (BusinessDocumentImportFields::definitions() as $def) {
            $value = $mapping[$def['key']] ?? null;
            if ($value === '' || $value === null) {
                $normalized[$def['key']] = null;

                continue;
            }
            $normalized[$def['key']] = (int) $value;
        }

        foreach (BusinessDocumentImportFields::definitions() as $def) {
            if ($def['required'] && $normalized[$def['key']] === null) {
                throw new \InvalidArgumentException('Missing required column mapping: '.$def['key']);
            }
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @param  array{line_name?: string, line_description?: string}  $options
     * @return array<string, mixed>
     */
    protected function rowToInvoiceData(
        Company $company,
        array $row,
        array $mapping,
        array $options,
        bool $validateOnly = false,
    ): array {
        $invoiceNumber = $this->cell($row, $mapping['invoice_number']);
        $clientName = $this->cell($row, $mapping['client_name']);
        $dateParser = new ImportDateParser((string) ($options['date_format'] ?? 'auto'));
        $issueDate = $dateParser->parse($this->cell($row, $mapping['issue_date']));
        $dueDate = $dateParser->parse($this->cell($row, $mapping['due_date']));
        $amount = $this->parseAmount($this->cell($row, $mapping['amount']));

        if ($invoiceNumber === null || $invoiceNumber === '') {
            throw new \InvalidArgumentException('Invoice number is required.');
        }
        if ($clientName === null || trim($clientName) === '') {
            throw new \InvalidArgumentException('Client name is required.');
        }
        if (! $issueDate) {
            throw new \InvalidArgumentException('Issue date is invalid or missing.');
        }
        if (! $dueDate) {
            throw new \InvalidArgumentException('Due date is invalid or missing.');
        }
        if ($amount === null || $amount <= 0) {
            throw new \InvalidArgumentException('Amount is invalid or missing.');
        }

        if (
            ! $validateOnly
            && BusinessDocument::query()
                ->where('company_id', $company->id)
                ->where('type', BusinessDocumentType::Invoice)
                ->where('number', $invoiceNumber)
                ->exists()
        ) {
            throw new \InvalidArgumentException('Invoice number already exists: '.$invoiceNumber);
        }

        $paidAt = $dateParser->parse($this->cell($row, $mapping['paid_at']));

        return [
            'invoice_number' => $invoiceNumber,
            'variable_symbol' => $this->cell($row, $mapping['variable_symbol']) ?? preg_replace('/\D/', '', $invoiceNumber),
            'client_name' => trim($clientName),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'amount' => $amount,
            'currency' => strtoupper($this->cell($row, $mapping['currency']) ?? $company->default_currency ?? 'EUR'),
            'paid' => $paidAt !== null,
            'paid_at' => $paidAt,
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @param  array{line_name?: string, line_description?: string, create_contacts?: bool}  $options
     * @return array{contact_created: bool, contact_linked: bool}
     */
    protected function importRow(Company $company, array $row, array $mapping, array $options): array
    {
        $data = $this->rowToInvoiceData($company, $row, $mapping, $options);

        $contactResult = $this->resolveContact(
            $company,
            $row,
            $mapping,
            (bool) ($options['create_contacts'] ?? true),
        );
        $contact = $contactResult['contact'];

        $lineName = trim((string) ($options['line_name'] ?? '')) ?: 'Imported item';
        $lineDescription = trim((string) ($options['line_description'] ?? '')) ?: null;

        $lines = [[
            'name' => $lineName,
            'description' => $lineDescription,
            'quantity' => 1,
            'unit' => 'ks.',
            'unit_price' => $data['amount'],
            'line_discount_percent' => 0,
            'tax_rate' => 0,
        ]];

        $dateParser = new ImportDateParser((string) ($options['date_format'] ?? 'auto'));

        $document = new BusinessDocument([
            'company_id' => $company->id,
            'company_contact_id' => $contact?->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => $data['paid'] ? BusinessDocumentStatus::Paid : BusinessDocumentStatus::Issued,
            'number' => $data['invoice_number'],
            'title' => 'Faktúra '.$data['invoice_number'],
            'variable_symbol' => $data['variable_symbol'],
            'constant_symbol' => $this->cell($row, $mapping['constant_symbol']),
            'specific_symbol' => $this->cell($row, $mapping['specific_symbol']),
            'issue_date' => $data['issue_date'],
            'delivery_date' => $dateParser->parse($this->cell($row, $mapping['delivery_date'])),
            'due_date' => $data['due_date'],
            'currency' => $data['currency'],
            'note_footer' => $company->legal_footer_note,
            'pdf_locale' => 'sk',
            'pdf_show_signature' => true,
            'pdf_show_payment_info' => true,
            'payment_btc_enabled' => false,
            'payment_bank_enabled' => false,
            'tags' => ['import'],
            'paid_at' => $data['paid'] ? $data['paid_at']?->startOfDay() : null,
            'amount_paid' => $data['paid'] ? $data['amount'] : null,
        ]);

        if ($contact) {
            $document->buyer_snapshot = BuyerSnapshot::fromContact($contact);
        }

        $document->setRelation('company', $company);
        $this->totalsCalculator->applyToDocument($document, $lines, 0);
        $document->save();

        foreach ($lines as $index => $line) {
            $amounts = $this->canonicalBuilder->computeLineAmounts($company, $line, $contact);
            BusinessDocumentLine::create([
                'business_document_id' => $document->id,
                'sort_order' => $index,
                'name' => $line['name'],
                'description' => $line['description'],
                'quantity' => 1,
                'unit' => $line['unit'],
                'unit_price' => $line['unit_price'],
                'line_discount_percent' => 0,
                'tax_rate' => $amounts['tax_rate'],
                'line_total' => number_format($amounts['gross'], 2, '.', ''),
            ]);
        }

        return [
            'contact_created' => $contactResult['created'],
            'contact_linked' => $contactResult['linked'],
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @return array{contact: CompanyContact|null, created: bool, linked: bool}
     */
    protected function resolveContact(
        Company $company,
        array $row,
        array $mapping,
        bool $createContacts = true,
    ): array {
        $name = trim((string) ($this->cell($row, $mapping['client_name']) ?? ''));
        if ($name === '') {
            return ['contact' => null, 'created' => false, 'linked' => false];
        }

        $ico = $this->cell($row, $mapping['client_registration_number']);
        $rowAttributes = $this->contactAttributesFromRow($row, $mapping, $name);

        $query = $company->contacts();
        if ($ico) {
            $existing = (clone $query)->where('registration_number', $ico)->first();
            if ($existing) {
                $this->fillMissingContactFields($existing, $rowAttributes);

                return ['contact' => $existing->fresh(), 'created' => false, 'linked' => true];
            }
        }

        $existing = (clone $query)
            ->where('name', $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like', $name)
            ->first();
        if ($existing) {
            $this->fillMissingContactFields($existing, $rowAttributes);

            return ['contact' => $existing->fresh(), 'created' => false, 'linked' => true];
        }

        if (! $createContacts) {
            return ['contact' => null, 'created' => false, 'linked' => false];
        }

        $contact = $company->contacts()->create($rowAttributes);

        return ['contact' => $contact, 'created' => true, 'linked' => false];
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @return array<string, mixed>
     */
    protected function contactAttributesFromRow(array $row, array $mapping, string $name): array
    {
        $country = $this->cell($row, $mapping['client_country']);
        $countryName = match (strtoupper((string) $country)) {
            'SK' => 'Slovensko',
            'CZ' => 'Česko',
            'AT' => 'Rakúsko',
            'HU' => 'Maďarsko',
            'PL' => 'Poľsko',
            'DE' => 'Nemecko',
            default => $country,
        };

        return array_filter([
            'name' => $name,
            'registration_number' => $this->cell($row, $mapping['client_registration_number']),
            'tax_id' => $this->cell($row, $mapping['client_tax_id']),
            'vat_id' => $this->cell($row, $mapping['client_vat_id']),
            'street' => $this->cell($row, $mapping['client_street']),
            'city' => $this->cell($row, $mapping['client_city']),
            'postal_code' => $this->cell($row, $mapping['client_postal_code']),
            'country' => $countryName,
            'phone' => $this->cell($row, $mapping['client_phone']),
            'email' => $this->cell($row, $mapping['client_email']),
            'is_active' => true,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function fillMissingContactFields(CompanyContact $contact, array $attributes): void
    {
        $updates = [];

        foreach ($attributes as $field => $value) {
            if ($field === 'is_active') {
                continue;
            }

            $current = $contact->{$field};
            if ($current === null || $current === '') {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $contact->update($updates);
        }
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    protected function invoiceNumberFromRow(array $row, array $mapping): ?string
    {
        return $this->cell($row, $mapping['invoice_number'] ?? null);
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

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return null;
    }

    protected function parseAmount(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
