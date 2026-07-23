<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\TestEfakturaConnectionRequest;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Services\Invoicing\Efaktura\ComplianceStatusSyncService;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Services\Invoicing\Efaktura\EfakturaConnectionTester;
use App\Services\Invoicing\Efaktura\EfakturaInboundService;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EfakturaController extends Controller
{
    public function compliance(Company $company, BusinessDocument $businessDocument): JsonResponse
    {
        $this->assertDocumentCompany($businessDocument, $company);

        $rows = BusinessDocumentCompliance::query()
            ->where('business_document_id', $businessDocument->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function send(
        Company $company,
        BusinessDocument $businessDocument,
        ComplianceSubmissionService $submissionService,
    ): JsonResponse {
        $this->assertDocumentCompany($businessDocument, $company);
        $this->assertEfakturaConfigured($company);

        $result = $submissionService->submitNow($businessDocument->fresh(['company', 'contact', 'lines']));

        return response()->json(['data' => [
            'status' => $result->status->value,
            'external_id' => $result->externalId,
            'message' => $result->message,
            'response_payload' => $result->responsePayload,
        ]]);
    }

    public function pollInbound(Company $company, EfakturaInboundService $inboundService): JsonResponse
    {
        $this->assertEfakturaConfigured($company);

        if (! CompanyEfakturaSettings::fromCompany($company)->inboundEnabled()) {
            throw ValidationException::withMessages([
                'efaktura_inbound_enabled' => ['Inbound polling is disabled for this company.'],
            ]);
        }

        $stats = $inboundService->pollCompany($company->fresh());

        return response()->json([
            'data' => array_merge($stats, [
                'polled_at' => CompanyEfakturaSettings::fromCompany($company->fresh())->publicPayload()['efaktura_inbound_last_poll_at'] ?? null,
            ]),
        ]);
    }

    public function refreshCompliance(
        Company $company,
        BusinessDocument $businessDocument,
        ComplianceStatusSyncService $statusSyncService,
    ): JsonResponse {
        $this->assertDocumentCompany($businessDocument, $company);
        $this->assertEfakturaConfigured($company);

        $statusSyncService->refreshDocument($businessDocument->fresh());

        $rows = BusinessDocumentCompliance::query()
            ->where('business_document_id', $businessDocument->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * One-shot SAPI-SK credential check. Runs BEFORE the company is fully
     * configured (that is the point of testing), so it only requires the
     * global flag and company eligibility; missing fields fall back to the
     * stored settings. Success stamps efaktura_connection_tested_at.
     */
    public function testConnection(
        TestEfakturaConnectionRequest $request,
        Company $company,
        EfakturaConnectionTester $tester,
    ): JsonResponse {
        if (! config('efaktura.enabled')) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura integration is disabled globally.'],
            ]);
        }

        if (! app(CompanyEfakturaEligibility::class)->supportsCompany($company)) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura is available only for Slovak companies registered as full VAT payers.'],
            ]);
        }

        $validated = $request->validated();
        $stored = CompanyEfakturaSettings::fromCompany($company);

        $result = $tester->test(
            (string) ($validated['efaktura_sapi_base_url'] ?? '') !== ''
                ? (string) $validated['efaktura_sapi_base_url']
                : $stored->sapiBaseUrl(),
            (string) ($validated['efaktura_sapi_client_id'] ?? '') !== ''
                ? (string) $validated['efaktura_sapi_client_id']
                : $stored->sapiClientId(),
            (string) ($validated['efaktura_sapi_client_secret'] ?? '') !== ''
                ? (string) $validated['efaktura_sapi_client_secret']
                : $stored->sapiClientSecret(),
        );

        $testedAt = null;
        if ($result['ok']) {
            $testedAt = Carbon::now()->toIso8601String();
            // getAttribute keeps the mixed shape (array cast vs string PHPDoc).
            $rawSettings = $company->getAttribute('app_settings');
            $settings = CompanyAppSettings::from(is_array($rawSettings) ? $rawSettings : null)->toArray();
            $settings['efaktura_connection_tested_at'] = $testedAt;
            $company->update(['app_settings' => $settings]);
        }

        return response()->json(['data' => array_merge($result, ['tested_at' => $testedAt])]);
    }

    protected function assertDocumentCompany(BusinessDocument $document, Company $company): void
    {
        if ($document->company_id !== $company->id) {
            abort(404);
        }
    }

    protected function assertEfakturaConfigured(Company $company): void
    {
        if (! config('efaktura.enabled')) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura integration is disabled globally.'],
            ]);
        }

        if (! app(CompanyEfakturaEligibility::class)->supportsCompany($company)) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura is available only for Slovak companies registered as full VAT payers.'],
            ]);
        }

        if (! CompanyEfakturaSettings::fromCompany($company)->configured()) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura credentials are not configured for this company.'],
            ]);
        }
    }
}
