<?php

namespace App\Services\Invoicing\Efaktura;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Jobs\SubmitBusinessDocumentCompliance;
use App\Models\AuditLog;
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
        if (app(SapiSkClient::class)->isEpostman($settings->sapiBaseUrl())) {
            return;
        }
        if (! $settings->autoSend()) {
            return;
        }

        if (! $this->gateway->supports($document)) {
            return;
        }

        // Issue can run inside mark-paid or another enclosing transaction.
        // A rollback must discard submission even when the queue runs inline.
        $documentId = $document->id;
        $document->getConnection()->afterCommit(static function () use ($documentId): void {
            SubmitBusinessDocumentCompliance::dispatch($documentId);
        });
    }

    public function submitNow(BusinessDocument $document): ComplianceSubmissionResult
    {
        $result = $this->gateway->submit($document);

        return app(EpostmanOutboundSubmissionService::class)->withLatestResult($document, $result, function (ComplianceSubmissionResult $current) use ($document): void {
            $row = $this->persist($document, $current);
            $this->auditSubmission($document, $current, $row);
        });
    }

    public function submitEphemeral(BusinessDocument $document): ComplianceSubmissionResult
    {
        return $this->gateway->submit($document);
    }

    protected function persist(BusinessDocument $document, ComplianceSubmissionResult $result): BusinessDocumentCompliance
    {
        $now = now();
        $provider = ComplianceProvider::tryFrom($this->gateway->provider()) ?? ComplianceProvider::Peppol;

        return BusinessDocumentCompliance::query()->updateOrCreate(
            [
                'business_document_id' => $document->id,
                'provider' => $provider,
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

    protected function auditSubmission(
        BusinessDocument $document,
        ComplianceSubmissionResult $result,
        BusinessDocumentCompliance $row,
    ): void {
        AuditLog::log('business_document.efaktura_submitted', 'business_document', $document->id, [
            'company_id' => $document->company_id,
            'number' => $document->number,
            'provider' => $this->gateway->provider(),
            'status' => $result->status->value,
            'external_id' => $result->externalId,
            'compliance_id' => $row->id,
            'message' => $result->message,
        ]);
    }
}
