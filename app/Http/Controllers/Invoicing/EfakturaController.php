<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Services\Invoicing\Efaktura\ComplianceStatusSyncService;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Services\Invoicing\Efaktura\EfakturaInboundService;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Http\JsonResponse;
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
