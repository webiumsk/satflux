<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Store;
use App\Services\Invoicing\CompanyBrandingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CompanyPermanentDeleteService
{
    public function __construct(
        protected CompanyBrandingService $brandingService,
    ) {}

    public function purgeSoftDeleted(bool $dryRun): int
    {
        $days = max(1, (int) config('data_retention.soft_deleted_companies_days', 30));
        $cutoff = Carbon::now()->subDays($days);

        $companies = Company::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->limit(max(1, (int) config('data_retention.batch_size', 200)))
            ->get();

        $count = 0;
        foreach ($companies as $company) {
            if ($dryRun) {
                $count++;

                continue;
            }
            $this->forceDelete($company);
            $count++;
        }

        if ($count > 0 && ! $dryRun) {
            Log::info('Data retention: companies force-deleted', ['count' => $count]);
        }

        return $count;
    }

    public function forceDelete(Company $company): void
    {
        Store::query()
            ->where('company_id', $company->id)
            ->update(['company_id' => null]);

        if ($company->logo_path) {
            $this->brandingService->deleteLogo($company->fresh());
        }
        if ($company->signature_stamp_path) {
            $this->brandingService->deleteSignatureStamp($company->fresh());
        }

        $company->forceDelete();
    }
}
