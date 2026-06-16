<?php

namespace App\Services;

use App\Enums\BusinessDocumentStatus;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Export;
use App\Models\IntegrationDocumentInbox;
use App\Services\Invoicing\BankStatementImportService;
use App\Services\Invoicing\BusinessExpenseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataRetentionService
{
    public function __construct(
        protected CompanyPermanentDeleteService $companyPermanentDelete,
        protected BankStatementImportService $bankImports,
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(bool $dryRun): array
    {
        $stats = [
            'webhook_events_deleted' => 0,
            'audit_logs_deleted' => 0,
            'export_files_deleted' => 0,
            'draft_documents_deleted' => 0,
            'companies_force_deleted' => 0,
            'bank_import_files_deleted' => 0,
            'cancelled_expenses_deleted' => 0,
            'integration_inbox_closed_deleted' => 0,
        ];

        $stats['webhook_events_deleted'] = $this->purgeWebhookEvents($dryRun);
        $stats['audit_logs_deleted'] = $this->purgeAuditLogs($dryRun);
        $stats['export_files_deleted'] = $this->purgeExportFiles($dryRun);
        $stats['draft_documents_deleted'] = $this->purgeStaleDraftDocuments($dryRun);
        $stats['companies_force_deleted'] = $this->companyPermanentDelete->purgeSoftDeleted($dryRun);
        $stats['bank_import_files_deleted'] = $dryRun
            ? 0
            : $this->bankImports->purgeOldImportFiles();
        $stats['cancelled_expenses_deleted'] = $this->expenseService->purgeCancelled($dryRun);
        $stats['integration_inbox_closed_deleted'] = $this->purgeClosedIntegrationInbox($dryRun);

        return $stats;
    }

    protected function purgeWebhookEvents(bool $dryRun): int
    {
        $days = max(1, (int) config('data_retention.webhook_events_days', 90));
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('webhook_events')
            ->whereNotNull('processed_at')
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();
        if ($count === 0 || $dryRun) {
            return $dryRun ? $count : 0;
        }

        $deleted = 0;
        $batch = max(1, (int) config('data_retention.batch_size', 200));

        do {
            $ids = (clone $query)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += DB::table('webhook_events')->whereIn('id', $ids)->delete();
        } while (true);

        Log::info('Data retention: webhook_events purged', ['count' => $deleted, 'cutoff' => $cutoff->toIso8601String()]);

        return $deleted;
    }

    protected function purgeAuditLogs(bool $dryRun): int
    {
        $days = max(1, (int) config('data_retention.audit_logs_days', 730));
        $cutoff = Carbon::now()->subDays($days);

        $query = AuditLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0 || $dryRun) {
            return $dryRun ? $count : 0;
        }

        $deleted = 0;
        $batch = max(1, (int) config('data_retention.batch_size', 200));

        do {
            $ids = (clone $query)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += AuditLog::query()->whereIn('id', $ids)->delete();
        } while (true);

        Log::info('Data retention: audit_logs purged', ['count' => $deleted, 'cutoff' => $cutoff->toIso8601String()]);

        return $deleted;
    }

    protected function purgeExportFiles(bool $dryRun): int
    {
        $days = max(1, (int) config('data_retention.export_files_days', 30));
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;
        $disk = Storage::disk('exports');

        $exports = Export::query()
            ->where('status', 'finished')
            ->where(function ($q) use ($cutoff) {
                $q->where('expires_at', '<', now())
                    ->orWhere('updated_at', '<', $cutoff);
            })
            ->whereNotNull('file_path')
            ->limit(500)
            ->get();

        foreach ($exports as $export) {
            if ($export->file_path && $disk->exists($export->file_path)) {
                if (! $dryRun) {
                    $disk->delete($export->file_path);
                }
                $deleted++;
            }
            if (! $dryRun) {
                $export->update([
                    'file_path' => null,
                    'signed_url' => null,
                ]);
            }
        }

        if (! $dryRun) {
            foreach ($disk->files() as $path) {
                $full = $disk->path($path);
                if (is_file($full) && filemtime($full) < $cutoff->timestamp) {
                    $disk->delete($path);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    protected function purgeStaleDraftDocuments(bool $dryRun): int
    {
        $days = max(30, (int) config('data_retention.draft_documents_days', 365));
        $cutoff = Carbon::now()->subDays($days);

        $query = BusinessDocument::query()
            ->where('status', BusinessDocumentStatus::Draft)
            ->where('updated_at', '<', $cutoff);

        $count = (clone $query)->count();
        if ($count === 0 || $dryRun) {
            return $dryRun ? $count : 0;
        }

        $deleted = 0;
        $batch = max(1, (int) config('data_retention.batch_size', 200));

        do {
            $ids = (clone $query)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += BusinessDocument::query()->whereIn('id', $ids)->delete();
        } while (true);

        Log::info('Data retention: draft business_documents purged', ['count' => $deleted]);

        return $deleted;
    }

    protected function purgeClosedIntegrationInbox(bool $dryRun): int
    {
        $days = max(0, (int) config('data_retention.integration_inbox_closed_days', 0));
        $query = IntegrationDocumentInbox::query()
            ->whereIn('status', [
                IntegrationDocumentInboxStatus::Imported,
                IntegrationDocumentInboxStatus::Dismissed,
            ]);

        if ($days > 0) {
            $cutoff = Carbon::now()->subDays($days);
            $query->where('updated_at', '<', $cutoff);
        }

        $count = (clone $query)->count();
        if ($count === 0 || $dryRun) {
            return $dryRun ? $count : 0;
        }

        $deleted = 0;
        $batch = max(1, (int) config('data_retention.batch_size', 200));

        do {
            $ids = (clone $query)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += IntegrationDocumentInbox::query()->whereIn('id', $ids)->delete();
        } while (true);

        Log::info('Data retention: integration_document_inbox closed rows purged', ['count' => $deleted]);

        return $deleted;
    }
}
