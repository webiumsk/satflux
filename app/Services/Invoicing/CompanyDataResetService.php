<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Models\BusinessExpense;
use App\Models\BusinessRecurringProfile;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyDocumentSequence;
use App\Models\EfakturaInboundReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyDataResetService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * Wipe operational invoicing data while keeping company profile, settings, and store links.
     *
     * @return array<string, int>
     */
    public function reset(Company $company): array
    {
        return DB::transaction(function () use ($company) {
            $stats = [
                'documents' => BusinessDocument::query()->where('company_id', $company->id)->count(),
                'contacts' => CompanyContact::query()->where('company_id', $company->id)->count(),
                'expenses' => BusinessExpense::query()->where('company_id', $company->id)->count(),
                'recurring_profiles' => BusinessRecurringProfile::query()->where('company_id', $company->id)->count(),
                'bank_transactions' => BankTransaction::query()->where('company_id', $company->id)->count(),
                'bank_import_batches' => BankImportBatch::query()->where('company_id', $company->id)->count(),
                'efaktura_inbound_receipts' => EfakturaInboundReceipt::query()->where('company_id', $company->id)->count(),
            ];

            BusinessDocument::query()
                ->where('company_id', $company->id)
                ->update(['source_document_id' => null]);

            BusinessDocument::query()->where('company_id', $company->id)->delete();
            BankTransaction::query()->where('company_id', $company->id)->delete();

            $disk = Storage::disk(config('bank_import.storage_disk', 'local'));
            BankImportBatch::query()
                ->where('company_id', $company->id)
                ->get()
                ->each(function (BankImportBatch $batch) use ($disk) {
                    if ($batch->storage_path && $disk->exists($batch->storage_path)) {
                        $disk->delete($batch->storage_path);
                    }
                });
            BankImportBatch::query()->where('company_id', $company->id)->delete();

            BusinessExpense::query()
                ->where('company_id', $company->id)
                ->with('attachments')
                ->get()
                ->each(fn (BusinessExpense $expense) => $this->expenseService->permanentlyDelete($expense));
            EfakturaInboundReceipt::query()->where('company_id', $company->id)->delete();
            BusinessRecurringProfile::query()->where('company_id', $company->id)->delete();
            CompanyContact::query()->where('company_id', $company->id)->delete();

            CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->get()
                ->each(function (CompanyDocumentSequence $series) {
                    $series->update([
                        'last_number' => 0,
                        'period_key' => $this->sequenceService->currentPeriodKey($series->reset_period),
                    ]);
                });

            AuditLog::log('company.data_reset', 'company', $company->id, $stats);

            return $stats;
        });
    }
}
