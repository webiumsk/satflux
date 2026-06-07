<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class BusinessExpenseBulkService
{
    public function __construct(
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * @return Builder<BusinessExpense>
     */
    public function filteredQuery(Company $company, Request $request): Builder
    {
        return $this->expenseService->filteredQuery($company, $request);
    }

    /**
     * @return Collection<int, BusinessExpense>
     */
    public function resolveExpenses(Company $company, Request $request): Collection
    {
        $query = $this->filteredQuery($company, $request);

        if ($request->boolean('select_all')) {
            return $query
                ->orderByDesc('issue_date')
                ->orderByDesc('created_at')
                ->get();
        }

        $ids = $request->input('expense_ids', []);

        return $query
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function markPaid(Collection $expenses): array
    {
        $processed = 0;
        $skipped = 0;

        foreach ($expenses as $expense) {
            if ($expense->status !== BusinessExpenseStatus::Recorded) {
                $skipped++;

                continue;
            }

            $this->expenseService->markPaid($expense);
            $processed++;
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function cancel(Collection $expenses): array
    {
        $processed = 0;
        $skipped = 0;

        foreach ($expenses as $expense) {
            if ($expense->status === BusinessExpenseStatus::Cancelled) {
                $skipped++;

                continue;
            }

            $this->expenseService->cancel($expense);
            $processed++;
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    public function downloadXlsx(Company $company, Collection $expenses): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'Internal number',
                'External number',
                'Title',
                'Status',
                'Total',
                'Currency',
                'Issue date',
                'Delivery date',
                'Due date',
                'Paid at',
                'Variable symbol',
            ],
        ]);

        $row = 2;
        foreach ($expenses as $expense) {
            $sheet->fromArray([
                [
                    $expense->internal_number,
                    $expense->external_number,
                    $expense->title,
                    $expense->status->value,
                    $expense->total,
                    $expense->currency,
                    $expense->issue_date?->format('Y-m-d'),
                    $expense->delivery_date?->format('Y-m-d'),
                    $expense->due_date?->format('Y-m-d'),
                    $expense->paid_at?->format('Y-m-d'),
                    $expense->variable_symbol,
                ],
            ], null, "A{$row}");
            $row++;
        }

        $path = Storage::disk('local')->path('temp/expenses-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        AuditLog::log('business_expense.bulk_xlsx', 'company', $company->id, [
            'count' => $expenses->count(),
        ]);

        return response()->download($path, 'expenses.xlsx')->deleteFileAfterSend(true);
    }

    public function downloadAttachmentsZip(Company $company, Collection $expenses): Response
    {
        $expenses->load('attachments');
        $withFiles = $expenses->filter(fn (BusinessExpense $e) => $e->hasAttachment());

        if ($withFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'expense_ids' => ['No selected expenses have attachments.'],
            ]);
        }

        $zipPath = Storage::disk('local')->path('temp/expense-attachments-'.uniqid().'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        foreach ($withFiles as $expense) {
            $files = $expense->attachments;
            if ($files->isEmpty() && $expense->attachment_path) {
                $disk = Storage::disk($expense->attachment_disk);
                if ($disk->exists($expense->attachment_path)) {
                    $name = $expense->original_filename ?? ('expense-'.$expense->internal_number.'.pdf');
                    $safe = preg_replace('/[^\w.\-]+/u', '_', $name) ?: 'attachment.pdf';
                    $zip->addFromString($expense->internal_number.'-'.$safe, $disk->get($expense->attachment_path));
                }

                continue;
            }

            foreach ($files as $index => $attachment) {
                $disk = Storage::disk($attachment->disk);
                if (! $disk->exists($attachment->path)) {
                    continue;
                }

                $name = $attachment->original_filename
                    ?? ('expense-'.$expense->internal_number.'-'.($index + 1).'.pdf');
                $safe = preg_replace('/[^\w.\-]+/u', '_', $name) ?: 'attachment.pdf';
                $prefix = $expense->internal_number;
                $entryName = $files->count() > 1
                    ? "{$prefix}-".($index + 1)."-{$safe}"
                    : "{$prefix}-{$safe}";
                $zip->addFromString($entryName, $disk->get($attachment->path));
            }
        }

        $zip->close();

        AuditLog::log('business_expense.bulk_attachments_zip', 'company', $company->id, [
            'count' => $withFiles->count(),
        ]);

        return response()->download($zipPath, 'expense-attachments.zip')->deleteFileAfterSend(true);
    }
}
