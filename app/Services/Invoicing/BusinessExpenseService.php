<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessExpenseStatus;
use App\Models\BankTransaction;
use App\Models\BusinessExpense;
use App\Models\BusinessExpenseAttachment;
use App\Models\Company;
use App\Support\Invoicing\ExpenseAttachmentMime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            ->withCount('attachments')
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
            'delivery_date' => array_key_exists('delivery_date', $data) ? $data['delivery_date'] : $data['issue_date'],
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
        $connection = $expense->getConnection();
        $connectionName = $connection->getName();

        return $connection->transaction(function () use ($expense, $data, $connectionName): BusinessExpense {
            // Edits never lock a bank movement: Company -> Expense is compatible with matching's Tx -> Company -> Expense.
            Company::on($connectionName)->whereKey($expense->company_id)->lockForUpdate()->firstOrFail();
            $locked = BusinessExpense::on($connectionName)->whereKey($expense->id)
                ->where('company_id', $expense->company_id)->lockForUpdate()->firstOrFail();
            if ($locked->getRawOriginal('status') === BusinessExpenseStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => ['Cancelled expenses cannot be edited.'],
                ]);
            }

            $changed = [];
            if (isset($data['total'])) {
                $before = $this->canonicalAmount($locked->getRawOriginal('total'));
                $after = $this->canonicalAmount($data['total']);
                if ($before === null || $after === null || $before !== $after) {
                    $changed[] = 'total';
                }
            }
            if (isset($data['currency'])) {
                $before = $this->canonicalCurrency($locked->getRawOriginal('currency'));
                $after = $this->canonicalCurrency($data['currency']);
                if ($before === null || $after === null || $before !== $after) {
                    $changed[] = 'currency';
                }
            }
            // Read only after the expense lock: a concurrent match must finish before financial evidence can change.
            if ($changed !== [] && BankTransaction::on($connectionName)->where('business_expense_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(array_fill_keys($changed, [
                    'Unmatch the linked bank movement before changing the expense total or currency.',
                ]));
            }

            $locked->fill([
                'external_number' => $data['external_number'] ?? $locked->external_number,
                'title' => $data['title'] ?? $locked->title,
                'variable_symbol' => $data['variable_symbol'] ?? $locked->variable_symbol,
                'constant_symbol' => $data['constant_symbol'] ?? $locked->constant_symbol,
                'specific_symbol' => $data['specific_symbol'] ?? $locked->specific_symbol,
                'issue_date' => $data['issue_date'] ?? $locked->issue_date,
                'delivery_date' => array_key_exists('delivery_date', $data) ? $data['delivery_date'] : $locked->delivery_date,
                'due_date' => $data['due_date'] ?? $locked->due_date,
                'total' => $data['total'] ?? $locked->total,
                'currency' => $data['currency'] ?? $locked->currency,
                'internal_note' => $data['internal_note'] ?? $locked->internal_note,
            ]);
            $locked->save();

            return $locked->fresh();
        });
    }

    /** Compare exact cent values, including harmless leading/trailing zeros and decimal exponent notation. */
    private function canonicalAmount(mixed $value): ?string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $formatted = number_format($value, 2, '.', '');
            if ((float) $formatted !== $value) {
                return null;
            }
            $value = $formatted;
        }
        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/\A\+?(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?\z/', trim((string) $value), $parts)) {
            return null;
        }
        $digits = ltrim($parts[1].($parts[2] ?? ''), '0');
        if ($digits === '') {
            return '0';
        }
        $exponent = $parts[3] ?? '0';
        if (strlen(ltrim($exponent, '+-0')) > 4) {
            return null;
        }
        $trimmed = rtrim($digits, '0');
        $scale = (int) $exponent - strlen($parts[2] ?? '') + strlen($digits) - strlen($trimmed);

        return $scale >= -2 ? $trimmed.'e'.$scale : null;
    }

    /** Normalize currency evidence for linked-expense edit comparisons. */
    private function canonicalCurrency(mixed $value): ?string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/\A[A-Z]{3}\z/', $currency) ? $currency : null;
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

    /** Clear independent payment state only after its bank relationship has been removed. */
    public function unmarkPaid(BusinessExpense $expense): BusinessExpense
    {
        return $expense->getConnection()->transaction(function () use ($expense): BusinessExpense {
            $locked = $this->lockUnlinkedExpense($expense);
            $locked->update([
                'status' => BusinessExpenseStatus::Recorded,
                'paid_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    /** Cancel an expense only after its bank relationship has been removed. */
    public function cancel(BusinessExpense $expense): BusinessExpense
    {
        return $expense->getConnection()->transaction(function () use ($expense): BusinessExpense {
            $locked = $this->lockUnlinkedExpense($expense);
            $locked->status = BusinessExpenseStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            return $locked->fresh();
        });
    }

    /** Lock company and expense state before reading bank links without acquiring a movement lock. */
    private function lockUnlinkedExpense(BusinessExpense $expense): BusinessExpense
    {
        $connectionName = $expense->getConnection()->getName();
        Company::on($connectionName)->whereKey($expense->company_id)->lockForUpdate()->firstOrFail();
        $locked = BusinessExpense::on($connectionName)->whereKey($expense->id)
            ->where('company_id', $expense->company_id)->lockForUpdate()->firstOrFail();
        if (BankTransaction::on($connectionName)->where('business_expense_id', $locked->id)->exists()) {
            throw ValidationException::withMessages([
                'status' => ['expense_linked_payment'],
            ]);
        }

        return $locked;
    }

    public function permanentlyDelete(BusinessExpense $expense): void
    {
        $expense->load('attachments');

        foreach ($expense->attachments as $attachment) {
            $this->deleteAttachmentFileRecord($attachment);
        }

        $this->deleteLegacyAttachmentFile($expense);

        $dir = "companies/{$expense->company_id}/expenses/{$expense->id}";
        try {
            Storage::disk('local')->deleteDirectory($dir);
        } catch (\Throwable $e) {
            Log::warning('Could not delete expense attachment directory', [
                'expense_id' => $expense->id,
                'path' => $dir,
                'error' => $e->getMessage(),
            ]);
        }

        $expense->delete();
    }

    public function purgeCancelled(bool $dryRun): int
    {
        $days = max(1, (int) config('data_retention.cancelled_expenses_days', 90));
        $cutoff = Carbon::now()->subDays($days);

        $query = BusinessExpense::query()
            ->where('status', BusinessExpenseStatus::Cancelled)
            ->where('cancelled_at', '<', $cutoff);

        $count = (clone $query)->count();
        if ($count === 0 || $dryRun) {
            return $dryRun ? $count : 0;
        }

        $deleted = 0;
        $batch = max(1, (int) config('data_retention.batch_size', 200));

        do {
            $expenses = (clone $query)
                ->with('attachments')
                ->orderBy('id')
                ->limit($batch)
                ->get();

            if ($expenses->isEmpty()) {
                break;
            }

            foreach ($expenses as $expense) {
                $this->permanentlyDelete($expense);
                $deleted++;
            }
        } while (true);

        Log::info('Data retention: cancelled business_expenses purged', [
            'count' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return $deleted;
    }

    public function storeAttachment(BusinessExpense $expense, UploadedFile $file): BusinessExpense
    {
        $this->addAttachment($expense, $file);

        return $expense->fresh(['attachments']);
    }

    public function addAttachment(BusinessExpense $expense, UploadedFile $file): BusinessExpenseAttachment
    {
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

        $attachment = $expense->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime' => ExpenseAttachmentMime::forFilename($filename),
            'size_bytes' => $file->getSize() ?: null,
        ]);

        $this->syncLegacyPrimaryAttachment($expense->fresh());

        return $attachment;
    }

    public function removeAttachment(BusinessExpenseAttachment $attachment): BusinessExpense
    {
        $expense = $attachment->expense;
        $this->deleteAttachmentFileRecord($attachment);
        $attachment->delete();
        $this->syncLegacyPrimaryAttachment($expense->fresh());

        return $expense->fresh(['attachments']);
    }

    public function deleteAttachment(BusinessExpense $expense): BusinessExpense
    {
        $expense->load('attachments');
        foreach ($expense->attachments as $attachment) {
            $this->deleteAttachmentFileRecord($attachment);
            $attachment->delete();
        }

        $this->deleteLegacyAttachmentFile($expense);
        $expense->update([
            'attachment_disk' => null,
            'attachment_path' => null,
            'original_filename' => null,
            'attachment_mime' => null,
        ]);

        return $expense->fresh(['attachments']);
    }

    protected function syncLegacyPrimaryAttachment(BusinessExpense $expense): void
    {
        $primary = $expense->attachments()->orderBy('created_at')->first();

        if ($primary) {
            $expense->update([
                'attachment_disk' => $primary->disk,
                'attachment_path' => $primary->path,
                'original_filename' => $primary->original_filename,
                'attachment_mime' => $primary->mime,
            ]);

            return;
        }

        $this->deleteLegacyAttachmentFile($expense);
        $expense->update([
            'attachment_disk' => null,
            'attachment_path' => null,
            'original_filename' => null,
            'attachment_mime' => null,
        ]);
    }

    protected function deleteAttachmentFileRecord(BusinessExpenseAttachment $attachment): void
    {
        if ($attachment->disk && $attachment->path) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }

    protected function deleteLegacyAttachmentFile(BusinessExpense $expense): void
    {
        if ($expense->attachment_disk && $expense->attachment_path) {
            Storage::disk($expense->attachment_disk)->delete($expense->attachment_path);
        }
    }
}
