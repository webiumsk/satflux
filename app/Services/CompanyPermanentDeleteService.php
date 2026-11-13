<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EfakturaOutboundAttempt;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Services\Invoicing\CompanyBrandingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('efaktura_outbound_attempts')
                    ->whereColumn('company_id', 'companies.id')
                    ->where(fn ($attempts) => $attempts->whereNull('external_id')->orWhere('external_id', '')->orWhereNotNull('request_encrypted'));
            })
            ->limit(max(1, (int) config('data_retention.batch_size', 200)))
            ->get();

        $count = 0;
        foreach ($companies as $company) {
            if ($dryRun) {
                $count++;

                continue;
            }
            if ($this->forceDelete($company, $cutoff)) {
                $count++;
            }
        }

        if ($count > 0 && ! $dryRun) {
            Log::info('Data retention: companies force-deleted', ['count' => $count]);
        }

        return $count;
    }

    public function forceDelete(Company $company, ?Carbon $deletedBefore = null): bool
    {
        return DB::transaction(function () use ($company, $deletedBefore): bool {
            $company = Company::withTrashed()->whereKey($company->id)->lockForUpdate()->first();
            if ($company === null || $company->deleted_at === null) {
                return false;
            }
            if ($deletedBefore !== null && Carbon::parse($company->deleted_at)->gte($deletedBefore)) {
                return false;
            }
            $attempts = EfakturaOutboundAttempt::query()->where('company_id', $company->id)->orderBy('id')->lockForUpdate()->get();
            if ($attempts->contains(fn (EfakturaOutboundAttempt $attempt) => $attempt->external_id === null
                || $attempt->external_id === '' || $attempt->request_encrypted !== null)) {
                return false;
            }
            // Custody is checked under the same lock before any file cleanup.
            if ($company->logo_path) {
                $this->brandingService->deleteLogo($company);
            }
            if ($company->signature_stamp_path) {
                $this->brandingService->deleteSignatureStamp($company);
            }
            $storeIds = Store::query()->where('company_id', $company->id)->lockForUpdate()->pluck('id')->all();
            StoreIntegration::query()->where(function ($query) use ($company, $storeIds): void {
                $query->where('company_id', $company->id)->orWhereIn('store_id', $storeIds);
            })->update(['is_active' => false]);
            Store::query()->whereIn('id', $storeIds)->update(['company_id' => null]);
            $company->forceDelete();

            return true;
        });
    }
}
