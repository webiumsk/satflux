<?php

namespace App\Services\Invoicing;

use App\Models\BusinessExpense;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

class BusinessExpenseAttachmentBulkService
{
    private const MAX_FILES = 500;

    public function __construct(
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * @return array{files: list<array{filename: string, matched_by: string|null, internal_number: string|null, expense_id: string|null, status: string}>, matched: int, unmatched: int}
     */
    public function preview(Company $company, UploadedFile $upload): array
    {
        $entries = $this->collectPdfEntries($upload);

        return $this->buildPreview($company, $entries);
    }

    /**
     * @return array{attached: int, skipped: int, errors: list<array{filename: string, message: string}>}
     */
    public function import(Company $company, UploadedFile $upload): array
    {
        $entries = $this->collectPdfEntries($upload);
        $preview = $this->buildPreview($company, $entries);

        $attached = 0;
        $skipped = 0;
        $errors = [];

        foreach ($preview['files'] as $index => $row) {
            if ($row['status'] !== 'matched') {
                $skipped++;
                if ($row['status'] === 'unmatched') {
                    $errors[] = [
                        'filename' => $row['filename'],
                        'message' => 'No matching expense found for this filename.',
                    ];
                }

                continue;
            }

            $entry = $entries[$index] ?? null;
            if (! $entry || ! $row['expense_id']) {
                $skipped++;
                $errors[] = ['filename' => $row['filename'], 'message' => 'Could not read file.'];

                continue;
            }

            $expense = BusinessExpense::query()
                ->where('company_id', $company->id)
                ->where('id', $row['expense_id'])
                ->first();

            if (! $expense) {
                $skipped++;
                $errors[] = ['filename' => $row['filename'], 'message' => 'Expense not found.'];

                continue;
            }

            try {
                $uploaded = $this->toUploadedFile($entry['path'], $entry['filename']);
                $this->expenseService->addAttachment($expense, $uploaded);
                $attached++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['filename' => $row['filename'], 'message' => $e->getMessage()];
            }
        }

        $this->cleanupEntries($entries);

        return [
            'attached' => $attached,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array{filename: string, path: string}>
     */
    protected function collectPdfEntries(UploadedFile $upload): array
    {
        $extension = strtolower($upload->getClientOriginalExtension() ?? '');

        if ($extension === 'zip') {
            return $this->extractZipPdfs($upload);
        }

        if ($extension !== 'pdf') {
            throw new \InvalidArgumentException('Upload a ZIP archive or PDF files.');
        }

        return [[
            'filename' => $upload->getClientOriginalName(),
            'path' => $upload->getRealPath() ?: $upload->path(),
        ]];
    }

    /**
     * @return list<array{filename: string, path: string}>
     */
    protected function extractZipPdfs(UploadedFile $upload): array
    {
        $zip = new ZipArchive;
        $path = $upload->getRealPath();
        if (! $path || $zip->open($path) !== true) {
            throw new \InvalidArgumentException('Could not open ZIP archive.');
        }

        $entries = [];
        $tempDir = sys_get_temp_dir().'/sf-expense-pdf-'.uniqid();
        mkdir($tempDir, 0700, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '/')) {
                continue;
            }

            $basename = basename($name);
            if (! str_ends_with(strtolower($basename), '.pdf')) {
                continue;
            }

            $target = $tempDir.'/'.$basename;
            if (file_exists($target)) {
                $target = $tempDir.'/'.uniqid().'_'.$basename;
            }

            copy('zip://'.$path.'#'.$name, $target);
            $entries[] = [
                'filename' => $basename,
                'path' => $target,
            ];

            if (count($entries) >= self::MAX_FILES) {
                break;
            }
        }

        $zip->close();

        if ($entries === []) {
            throw new \InvalidArgumentException('ZIP archive contains no PDF files.');
        }

        return $entries;
    }

    /**
     * @param  list<array{filename: string, path: string}>  $entries
     * @return array{files: list<array<string, mixed>>, matched: int, unmatched: int}
     */
    protected function buildPreview(Company $company, array $entries): array
    {
        $expenses = BusinessExpense::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->get();

        $files = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($entries as $entry) {
            $match = $this->matchExpense($expenses, $entry['filename']);
            $status = 'unmatched';

            if ($match['expense']) {
                $status = 'matched';
                $matched++;
            } else {
                $unmatched++;
            }

            $files[] = [
                'filename' => $entry['filename'],
                'matched_by' => $match['matched_by'],
                'internal_number' => $match['expense']?->internal_number,
                'expense_id' => $match['expense']?->id,
                'status' => $status,
            ];
        }

        return [
            'files' => $files,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @param  Collection<int, BusinessExpense>  $expenses
     * @return array{expense: BusinessExpense|null, matched_by: string|null}
     */
    protected function matchExpense(Collection $expenses, string $filename): array
    {
        $haystack = Str::ascii(mb_strtolower(pathinfo($filename, PATHINFO_FILENAME)));

        $best = null;
        $bestField = null;
        $bestLength = 0;

        foreach ($expenses as $expense) {
            foreach ([
                'internal_number' => $expense->internal_number,
                'external_number' => $expense->external_number,
                'variable_symbol' => $expense->variable_symbol,
            ] as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $needle = Str::ascii(mb_strtolower((string) $value));
                if (strlen($needle) < 2) {
                    continue;
                }

                if (str_contains($haystack, $needle) && strlen($needle) > $bestLength) {
                    $best = $expense;
                    $bestField = $field;
                    $bestLength = strlen($needle);
                }
            }
        }

        return [
            'expense' => $best,
            'matched_by' => $bestField,
        ];
    }

    protected function toUploadedFile(string $path, string $originalName): UploadedFile
    {
        return new UploadedFile($path, $originalName, 'application/pdf', null, true);
    }

    /**
     * @param  list<array{filename: string, path: string}>  $entries
     */
    protected function cleanupEntries(array $entries): void
    {
        $dirs = [];
        foreach ($entries as $entry) {
            $dir = dirname($entry['path']);
            if (str_contains($dir, 'sf-expense-pdf-')) {
                $dirs[$dir] = true;
                if (is_file($entry['path'])) {
                    @unlink($entry['path']);
                }
            }
        }

        foreach (array_keys($dirs) as $dir) {
            @rmdir($dir);
        }
    }
}
