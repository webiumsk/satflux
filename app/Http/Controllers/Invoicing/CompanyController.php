<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\ResetCompanyDataRequest;
use App\Http\Requests\Invoicing\StoreCompanyRequest;
use App\Http\Requests\Invoicing\UpdateCompanyAppSettingsRequest;
use App\Http\Requests\Invoicing\UpdateCompanyRequest;
use App\Http\Requests\Invoicing\UpdateCompanyStoresRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Store;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\CompanyBrandingService;
use App\Services\Invoicing\CompanyDataResetService;
use App\Services\Invoicing\DocumentSequenceService;
use App\Support\Invoicing\CompanyAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['contacts', 'documents'])
            ->orderBy('legal_name')
            ->get();

        return response()->json(['data' => $companies]);
    }

    public function store(
        StoreCompanyRequest $request,
        DocumentSequenceService $sequenceService,
        BankInboundAddressService $inboundAddressService,
    ): JsonResponse {
        $validated = $request->validated();
        $storeId = $validated['store_id'] ?? null;
        unset($validated['store_id']);

        $vatStatus = $validated['vat_status'] ?? (($validated['vat_payer'] ?? false) ? 'payer' : 'none');

        $company = Company::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'country' => $request->input('country', 'SK'),
            'default_currency' => $request->input('default_currency', 'EUR'),
            'vat_status' => $vatStatus,
            'vat_payer' => in_array($vatStatus, ['payer', 'partial'], true),
            'bank_inbound_token' => $inboundAddressService->generateUniqueToken(),
        ]);

        if ($storeId) {
            Store::query()
                ->where('user_id', $request->user()->id)
                ->where('id', $storeId)
                ->update(['company_id' => $company->id]);
        }

        $sequenceService->seedDefaultsForCompany($company);

        AuditLog::log('company.created', 'company', $company->id);

        $company->load('stores:id,name,company_id,default_currency');

        return response()->json(['data' => $company], 201);
    }

    public function show(Company $company, CompanyBrandingService $brandingService): JsonResponse
    {
        $company->load(['stores:id,name,company_id,default_currency', 'contacts' => fn ($q) => $q->where('is_active', true)]);

        return response()->json([
            'data' => $this->companyPayload($company, $brandingService),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company, CompanyBrandingService $brandingService): JsonResponse
    {
        $validated = $request->validated();
        if (array_key_exists('vat_status', $validated)) {
            $validated['vat_payer'] = in_array($validated['vat_status'], ['payer', 'partial'], true);
        } elseif (array_key_exists('vat_payer', $validated) && ! array_key_exists('vat_status', $validated)) {
            $validated['vat_status'] = $validated['vat_payer'] ? 'payer' : 'none';
        }

        $company->update($validated);
        $fresh = $company->fresh();

        return response()->json([
            'data' => $this->companyPayload($fresh, $brandingService),
        ]);
    }

    public function updateAppSettings(
        UpdateCompanyAppSettingsRequest $request,
        Company $company,
        CompanyBrandingService $brandingService,
    ): JsonResponse {
        $incoming = $request->validatedSettings();
        $eligibility = app(\App\Support\Invoicing\CompanyEfakturaEligibility::class);
        if ($eligibility->efakturaSettingKeys($incoming) !== []) {
            if (! $eligibility->supportsCompany($company)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'efaktura' => ['E-faktura settings are available only for full VAT payers.'],
                ]);
            }
        }
        $current = CompanyAppSettings::from($company->app_settings)->toArray();
        $merged = \App\Support\Invoicing\CompanyEfakturaSettings::mergeIncoming(
            array_merge($current, $incoming),
            $incoming,
        );
        unset($merged['efaktura_sapi_client_secret']);
        $company->update([
            'app_settings' => $merged,
        ]);

        AuditLog::log('company.app_settings_updated', 'company', $company->id);

        return response()->json([
            'data' => $this->companyPayload($company->fresh(), $brandingService),
        ]);
    }

    public function resetData(
        ResetCompanyDataRequest $request,
        Company $company,
        CompanyDataResetService $resetService,
    ): JsonResponse {
        $stats = $resetService->reset($company);

        return response()->json([
            'message' => 'Company operational data reset.',
            'data' => $stats,
        ]);
    }

    public function destroy(Company $company): JsonResponse
    {
        Store::query()
            ->where('company_id', $company->id)
            ->update(['company_id' => null]);

        $company->delete();

        return response()->json(['message' => 'Company deleted']);
    }

    public function updateStores(UpdateCompanyStoresRequest $request, Company $company): JsonResponse
    {
        $storeIds = $request->input('store_ids', []);
        $owned = Store::query()
            ->where('user_id', $company->user_id)
            ->whereIn('id', $storeIds)
            ->pluck('id')
            ->all();

        Store::query()
            ->where('user_id', $company->user_id)
            ->where('company_id', $company->id)
            ->whereNotIn('id', $owned)
            ->update(['company_id' => null]);

        Store::query()
            ->whereIn('id', $owned)
            ->update(['company_id' => $company->id]);

        return response()->json([
            'data' => $company->fresh()->load('stores:id,name,company_id,default_currency'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function companyPayload(Company $company, CompanyBrandingService $brandingService): array
    {
        return array_merge(
            $company->toArray(),
            ['app_settings' => $company->resolvedAppSettings()],
            ['email_settings' => $company->resolvedEmailSettings()],
            $brandingService->brandingMeta($company),
        );
    }
}
