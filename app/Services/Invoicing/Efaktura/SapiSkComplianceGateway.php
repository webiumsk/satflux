<?php

namespace App\Services\Invoicing\Efaktura;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\EuStructuredDocumentExport;
use App\Support\Invoicing\SkUblProfile;

class SapiSkComplianceGateway implements ComplianceSubmissionGateway
{
    public function __construct(
        protected SapiSkClient $client,
        protected BusinessDocumentUblService $ublService,
    ) {}

    public function provider(): string
    {
        return ComplianceProvider::Peppol->value;
    }

    public function supports(BusinessDocument $document): bool
    {
        if (! config('efaktura.enabled')) {
            return false;
        }

        if (! EuStructuredDocumentExport::supports($document)) {
            return false;
        }

        if ($document->status !== BusinessDocumentStatus::Issued) {
            return false;
        }

        if (! in_array($document->type, [BusinessDocumentType::Invoice, BusinessDocumentType::CreditNote], true)) {
            return false;
        }

        $document->loadMissing(['company', 'contact']);
        $company = $document->company;

        if ($company->jurisdiction !== CompanyJurisdiction::EuSk) {
            return false;
        }

        $settings = CompanyEfakturaSettings::fromCompany($company);
        if (! $settings->configured()) {
            return false;
        }

        if ($document->contact === null) {
            return false;
        }

        $contactCountry = trim((string) ($document->contact->country ?? ''));
        if ($contactCountry === '') {
            return false;
        }

        return SkUblProfile::countryCode($document->contact) === 'SK';
    }

    public function submit(BusinessDocument $document): \App\Support\Invoicing\ComplianceSubmissionResult
    {
        if (! $this->supports($document)) {
            return new \App\Support\Invoicing\ComplianceSubmissionResult(
                status: ComplianceSubmissionStatus::Skipped,
                message: 'Document is not eligible for SAPI-SK submission.',
            );
        }

        $document->loadMissing(['company', 'contact', 'lines']);
        $settings = CompanyEfakturaSettings::fromCompany($document->company);

        try {
            $ubl = $this->ublService->xml($document, auditDownload: false);
            $token = $this->client->accessToken(
                (string) $settings->sapiClientId(),
                (string) $settings->sapiClientSecret(),
            );

            $response = $this->client->sendDocument(
                $token,
                (string) $settings->peppolParticipantId(),
                $ubl,
                $document->id,
            );

            $externalId = (string) ($response['id'] ?? $response['document_id'] ?? $response['message_id'] ?? '');

            return new \App\Support\Invoicing\ComplianceSubmissionResult(
                status: ComplianceSubmissionStatus::Submitted,
                externalId: $externalId !== '' ? $externalId : null,
                responsePayload: $response,
            );
        } catch (\Throwable $e) {
            report($e);

            return new \App\Support\Invoicing\ComplianceSubmissionResult(
                status: ComplianceSubmissionStatus::Failed,
                message: $e->getMessage(),
            );
        }
    }
}
