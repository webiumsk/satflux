<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreCompanyAutoIssueProfileRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyAutoIssueProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Opt-in headless invoicing profile (WooCommerce auto-issue, P3). Routes are
 * guarded by EnsureCompanyOwnership; the profile carries only the company
 * invoice-header snapshot + delivery preference. Email settings persist on
 * the Company row via the existing email-settings endpoint.
 */
class CompanyAutoIssueProfileController extends Controller
{
    public function show(Company $company): JsonResponse
    {
        $profile = CompanyAutoIssueProfile::query()
            ->where('company_id', $company->id)
            ->first();

        return response()->json([
            'data' => $profile ? $this->serialize($profile) : null,
        ]);
    }

    public function update(StoreCompanyAutoIssueProfileRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

        $profile = CompanyAutoIssueProfile::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'profile_json' => [
                    'company' => $validated['company'],
                    'local_high_counters' => $validated['local_high_counters'] ?? [],
                ],
                'auto_email' => (bool) ($validated['auto_email'] ?? true),
            ],
        );

        AuditLog::log('company.auto_issue_profile_updated', 'company', $company->id, [
            'auto_email' => $profile->auto_email,
        ]);

        return response()->json(['data' => $this->serialize($profile)]);
    }

    public function destroy(Company $company): Response
    {
        CompanyAutoIssueProfile::query()
            ->where('company_id', $company->id)
            ->delete();

        AuditLog::log('company.auto_issue_profile_deleted', 'company', $company->id);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(CompanyAutoIssueProfile $profile): array
    {
        return [
            'company_id' => $profile->company_id,
            'auto_email' => $profile->auto_email,
            'synced_at' => $profile->updated_at?->toIso8601String(),
        ];
    }
}
