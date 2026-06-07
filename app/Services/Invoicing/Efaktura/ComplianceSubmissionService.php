<?php

namespace App\Services\Invoicing\Efaktura;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Jobs\SubmitBusinessDocumentCompliance;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\ComplianceSubmissionResult;

class ComplianceSubmissionService
{
    public function __construct(
        protected ComplianceSubmissionGateway $gateway,
    ) {}

    public function queueIfEligible(BusinessDocument $document): void
    {
        $document->loadMissing('company');

        if (! config('efaktura.enabled')) {
            return;
        }

        $settings = CompanyEfakturaSettings::fromCompany($document->company);
        if (! $settings->autoSend()) {
            return;
        }

        if (! $this->gateway->supports($document)) {
            return;
        }

        SubmitBusinessDocumentCompliance::dispatch($document->id);
    }

    public function submitNow(BusinessDocument $document): ComplianceSubmissionResult
    {
        $result = $this->gateway->submit($document);
        $this->persist($document, $result);

        return $result;
    }

    protected function persist(BusinessDocument $document, ComplianceSubmissionResult $result): BusinessDocumentCompliance
    {
        $now = now();

        return BusinessDocumentCompliance::query()->updateOrCreate(
            [
                'business_document_id' => $document->id,
                'provider' => ComplianceProvider::Peppol,
            ],
            [
                'status' => $result->status,
                'external_id' => $result->externalId,
                'response_payload' => $result->responsePayload,
                'qr_payload' => $result->qrPayload,
                'submitted_at' => in_array($result->status, [
                    ComplianceSubmissionStatus::Submitted,
                    ComplianceSubmissionStatus::Approved,
                    ComplianceSubmissionStatus::Rejected,
                    ComplianceSubmissionStatus::Failed,
                ], true) ? $now : null,
                'resolved_at' => in_array($result->status, [
                    ComplianceSubmissionStatus::Approved,
                    ComplianceSubmissionStatus::Rejected,
                    ComplianceSubmissionStatus::Failed,
                    ComplianceSubmissionStatus::Skipped,
                ], true) ? $now : null,
            ],
        );
    }
}
