<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\BusinessExpense;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessExpenseService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
    ) {}

    /**
     * @return Builder<BusinessExpense>
     */
    public function filteredQuery(Company $company, Request $request): Builder
    {
        $query = BusinessExpense::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', BusinessExpenseStatus::Cancelled);

        if ($request->filled('issue_from')) {
            $query->whereDate('issue_date', '>=', $request->get('issue_from'));
        }

        if ($request->filled('issue_to')) {
            $query->whereDate('issue_date', '<=', $request->get('issue_to'));
        } elseif ($request->filled('year')) {
            $query->whereYear('issue_date', (int) $request->get('year'));
        }

        $filter = $request->get('filter', 'all');
        if ($filter === 'paid') {
            $query->where('status', BusinessExpenseStatus::Paid);
        } elseif ($filter === 'unpaid') {
            $query->where('status', BusinessExpenseStatus::Recorded);
        } elseif ($filter === 'overdue') {
            $query->where('status', BusinessExpenseStatus::Recorded)
                ->whereDate('due_date', '<', now()->toDateString());
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, bool $markPaid = false): BusinessExpense
    {
        $internalNumber = $this->sequenceService->nextNumber($company, 'expense');

        $expense = new BusinessExpense([
            'company_id' => $company->id,
            'status' => $markPaid ? BusinessExpenseStatus::Paid : BusinessExpenseStatus::Recorded,
            'internal_number' => $internalNumber,
            'external_number' => $data['external_number'] ?? null,
            'title' => $data['title'] ?? null,
            'variable_symbol' => $data['variable_symbol'] ?? null,
            'constant_symbol' => $data['constant_symbol'] ?? null,
            'specific_symbol' => $data['specific_symbol'] ?? null,
            'issue_date' => $data['issue_date'],
            'delivery_date' => $data['delivery_date'] ?? $data['issue_date'],
            'due_date' => $data['due_date'] ?? null,
            'total' => $data['total'],
            'currency' => $data['currency'] ?? $company->default_currency ?? 'EUR',
            'internal_note' => $data['internal_note'] ?? null,
            'paid_at' => $markPaid ? now() : null,
        ]);

        $expense->save();

        return $expense->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BusinessExpense $expense, array $data): BusinessExpense
    {
        if ($expense->status === BusinessExpenseStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled expenses cannot be edited.'],
            ]);
        }

        $expense->fill([
            'external_number' => $data['external_number'] ?? $expense->external_number,
            'title' => $data['title'] ?? $expense->title,
            'variable_symbol' => $data['variable_symbol'] ?? $expense->variable_symbol,
            'constant_symbol' => $data['constant_symbol'] ?? $expense->constant_symbol,
            'specific_symbol' => $data['specific_symbol'] ?? $expense->specific_symbol,
            'issue_date' => $data['issue_date'] ?? $expense->issue_date,
            'delivery_date' => $data['delivery_date'] ?? $expense->delivery_date,
            'due_date' => $data['due_date'] ?? $expense->due_date,
            'total' => $data['total'] ?? $expense->total,
            'currency' => $data['currency'] ?? $expense->currency,
            'internal_note' => $data['internal_note'] ?? $expense->internal_note,
        ]);

        $expense->save();

        return $expense->fresh();
    }

    public function duplicate(Company $company, BusinessExpense $source): BusinessExpense
    {
        $internalNumber = $this->sequenceService->nextNumber($company, 'expense');

        $copy = new BusinessExpense([
            'company_id' => $company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => $internalNumber,
            'external_number' => $source->external_number,
            'title' => $source->title,
            'variable_symbol' => $source->variable_symbol,
            'constant_symbol' => $source->constant_symbol,
            'specific_symbol' => $source->specific_symbol,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => null,
            'total' => 0,
            'currency' => $source->currency,
            'internal_note' => $source->internal_note,
        ]);

        $copy->save();

        return $copy->fresh();
    }

    public function markPaid(BusinessExpense $expense): BusinessExpense
    {
        if ($expense->status === BusinessExpenseStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled expenses cannot be marked as paid.'],
            ]);
        }

        $expense->update([
            'status' => BusinessExpenseStatus::Paid,
            'paid_at' => now(),
        ]);

        return $expense->fresh();
    }

    public function unmarkPaid(BusinessExpense $expense): BusinessExpense
    {
        $expense->update([
            'status' => BusinessExpenseStatus::Recorded,
            'paid_at' => null,
        ]);

        return $expense->fresh();
    }

    public function cancel(BusinessExpense $expense): BusinessExpense
    {
        $expense->update(['status' => BusinessExpenseStatus::Cancelled]);

        return $expense->fresh();
    }

    public function storeAttachment(BusinessExpense $expense, UploadedFile $file): BusinessExpense
    {
        $this->deleteAttachmentFile($expense);

        $disk = 'local';
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $filename = ($safeName !== '' ? $safeName.'-' : '').uniqid().'.'.$ext;
        $path = "companies/{$expense->company_id}/expenses/{$expense->id}/{$filename}";

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        $expense->update([
            'attachment_disk' => $disk,
            'attachment_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ]);

        return $expense->fresh();
    }

    public function deleteAttachment(BusinessExpense $expense): BusinessExpense
    {
        $this->deleteAttachmentFile($expense);
        $expense->update([
            'attachment_disk' => null,
            'attachment_path' => null,
            'original_filename' => null,
            'attachment_mime' => null,
        ]);

        return $expense->fresh();
    }

    protected function deleteAttachmentFile(BusinessExpense $expense): void
    {
        if ($expense->attachment_disk && $expense->attachment_path) {
            Storage::disk($expense->attachment_disk)->delete($expense->attachment_path);
        }
    }
}
