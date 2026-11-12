<?php

namespace App\Services\Invoicing\Efaktura;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\BusinessDocumentStatus;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\EphemeralEfakturaSubmission;
use App\Models\User;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\ComplianceSubmissionResult;
use App\Support\Invoicing\Efaktura\SapiSkDocumentStatusMapper;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EphemeralEfakturaSubmissionService
{
    public function __construct(
        protected ComplianceSubmissionGateway $gateway,
        protected ComplianceSubmissionService $submissionService,
        protected SapiSkClient $client,
        protected CompanyEfakturaEligibility $eligibility,
    ) {}

    public function resolveBridgeCompany(User $user): ?Company
    {
        return Company::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->first(function (Company $company) {
                if (! $this->eligibility->supportsCompany($company)) {
                    return false;
                }

                return CompanyEfakturaSettings::fromCompany($company)->configured();
            });
    }

    public function assertBridgeCompany(User $user, ?Company $issuer = null, ?string $companyId = null): Company
    {
        if ($companyId !== null) {
            $company = Company::query()->where('user_id', $user->id)->find($companyId);
            abort_unless($company !== null, 404);
            $this->assertCompanyConfigured($company);

            return $company;
        }
        $issuerSettings = $issuer !== null ? CompanyEfakturaSettings::fromCompany($issuer) : null;
        $explicitUrl = $issuerSettings?->values['efaktura_sapi_base_url'] ?? null;
        $customConnection = is_string($explicitUrl) && trim($explicitUrl) !== '' && ! $this->client->isEpostman($explicitUrl);
        if ($issuer !== null && ! $customConnection) {
            $issuerSettings = CompanyEfakturaSettings::fromCompany($issuer);
            $matches = Company::query()->where('user_id', $user->id)->get()->filter(function (Company $candidate) use ($issuer): bool {
                $settings = CompanyEfakturaSettings::fromCompany($candidate);
                if (! $settings->configured() || ! $this->client->isEpostman($settings->sapiBaseUrl())) {
                    return false;
                }
                try {
                    app(EpostmanOutboundSubmissionService::class)->assertIssuerMatches($issuer, $candidate);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            });
            if ($matches->count() === 1) {
                return $matches->first();
            }
            if ($matches->count() > 1 || $this->client->isEpostman($issuerSettings->sapiBaseUrl())) {
                throw ValidationException::withMessages(['bridge_company_id' => ['Select the connected company matching this invoice issuer.']]);
            }
        }
        $company = $this->resolveBridgeCompany($user);
        if ($issuer !== null && $company !== null && $this->client->isEpostman(CompanyEfakturaSettings::fromCompany($company)->sapiBaseUrl())) {
            throw ValidationException::withMessages(['bridge_company_id' => ['No unique connected company matches this invoice issuer.']]);
        }
        if (! $company) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura credentials require a server company with SAPI-SK configured on your account.'],
            ]);
        }

        if (! config('efaktura.enabled')) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura integration is disabled globally.'],
            ]);
        }

        return $company;
    }

    /**
     * Company must be globally enabled, eligible (SK full VAT payer) and configured.
     *
     * @throws ValidationException
     */
    public function assertCompanyConfigured(Company $company): void
    {
        if (! config('efaktura.enabled')) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura integration is disabled globally.'],
            ]);
        }

        if (! $this->eligibility->supportsCompany($company)) {
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

    /**
     * Ephemeral e-faktura eligibility: the document must be issued, the
     * credentials company (bridge merged with snapshot overrides) must be
     * configured, and the gateway must support the document. Rebinds the
     * document's company relation to the credentials company.
     *
     * @throws ValidationException
     */
    public function assertEphemeralDocumentEligible(BusinessDocument $document, Company $bridgeCompany): void
    {
        if ($document->status !== BusinessDocumentStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => ['Issue the document before sending e-faktura.'],
            ]);
        }

        $snapshotCompany = $document->company;
        if ($this->client->isEpostman(CompanyEfakturaSettings::fromCompany($bridgeCompany)->sapiBaseUrl()) || $this->client->isEpostman(CompanyEfakturaSettings::fromCompany($snapshotCompany)->sapiBaseUrl())) {
            $bridgeCompany = $bridgeCompany->fresh() ?? $bridgeCompany;
            app(EpostmanOutboundSubmissionService::class)->assertIssuerMatches($snapshotCompany, $bridgeCompany);
            $stored = CompanyEfakturaSettings::fromCompany($bridgeCompany);
            $snapshot = CompanyEfakturaSettings::fromCompany($snapshotCompany);
            foreach (['efaktura_sapi_base_url' => $stored->sapiBaseUrl(), 'efaktura_sapi_client_id' => $stored->sapiClientId(), 'efaktura_peppol_participant_id' => $stored->peppolParticipantId()] as $key => $expected) {
                $incoming = $snapshot->values[$key] ?? null;
                if (is_string($incoming) && trim($incoming) !== '' && rtrim(trim($incoming), '/') !== $expected) {
                    throw ValidationException::withMessages(['efaktura' => ['The invoice connection differs from its connected company. Synchronize settings first.']]);
                }
            }
            $this->assertCompanyConfigured($bridgeCompany);
            $credentialsCompany = clone $snapshotCompany;
            $credentialsCompany->id = $bridgeCompany->id;
            $credentialsCompany->user_id = $bridgeCompany->user_id;
            $credentialsCompany->setAttribute('app_settings', CompanyEfakturaSettings::withoutEnterpriseSecrets($bridgeCompany->getAttribute('app_settings') ?? []));
            $document->setRelation('company', $credentialsCompany);
            if (! $this->gateway->supports($document)) {
                throw ValidationException::withMessages(['document' => ['Document is not eligible for e-faktura.']]);
            }

            return;
        }
        $credentialsCompany = $bridgeCompany->replicate();
        $credentialsCompany->exists = false;
        $credentialsCompany->id = $bridgeCompany->id;

        $snapshotSettings = CompanyEfakturaSettings::fromCompany($snapshotCompany);
        if ($snapshotSettings->configured()) {
            $credentialsCompany->app_settings = is_array($snapshotCompany->app_settings)
                ? $snapshotCompany->app_settings
                : [];
            $credentialsCompany->jurisdiction = $snapshotCompany->jurisdiction ?? $credentialsCompany->jurisdiction;
            $credentialsCompany->vat_payer = $snapshotCompany->vat_payer ?? $credentialsCompany->vat_payer;
            $credentialsCompany->vat_status = $snapshotCompany->vat_status ?? $credentialsCompany->vat_status;
        }

        $this->assertCompanyConfigured($credentialsCompany);

        $document->setRelation('company', $credentialsCompany);

        if (! $this->gateway->supports($document)) {
            throw ValidationException::withMessages([
                'document' => ['Document is not eligible for e-faktura (issued SK B2B invoice or credit note required).'],
            ]);
        }
    }

    public function submit(
        User $user,
        Company $bridgeCompany,
        BusinessDocument $document,
        string $evoluDocumentId,
    ): ComplianceSubmissionResult {
        $bridgeCompany = $bridgeCompany->fresh() ?? $bridgeCompany;

        if ($bridgeCompany->user_id !== $user->id) {
            abort(403, 'Unauthorized access to company');
        }

        $documentCompany = $document->company;
        if (! CompanyEfakturaSettings::fromCompany($documentCompany)->configured()) {
            $document->setRelation('company', $bridgeCompany);
        }

        $result = $this->submissionService->submitEphemeral($document);

        return app(EpostmanOutboundSubmissionService::class)->withLatestResult($document, $result, function (ComplianceSubmissionResult $current) use ($user, $bridgeCompany, $evoluDocumentId): void {
            $this->persistSubmission($user, $bridgeCompany, $evoluDocumentId, $current);
            AuditLog::log('business_document.ephemeral_efaktura_submitted', 'company', (string) $bridgeCompany->id, [
                'bridge_company_id' => $bridgeCompany->id,
                'evolu_document_id' => $evoluDocumentId,
                'status' => $current->status->value,
                'external_id' => $current->externalId,
                'message' => $current->message,
            ], $user->id);
        });
    }

    public function latestForDocument(User $user, string $evoluDocumentId, ?string $companyId = null): ?EphemeralEfakturaSubmission
    {
        return EphemeralEfakturaSubmission::query()
            ->where('user_id', $user->id)
            ->where('evolu_document_id', $evoluDocumentId)
            ->when($companyId !== null, fn ($query) => $query->where('bridge_company_id', $companyId))
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Latest submission per Evolu document id - powers the "e" badge in the
     * local-first invoice list.
     *
     * @param  list<string>  $evoluDocumentIds
     * @return array<string, EphemeralEfakturaSubmission>
     */
    public function latestForDocuments(User $user, array $evoluDocumentIds): array
    {
        $latest = [];

        EphemeralEfakturaSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('evolu_document_id', $evoluDocumentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->each(function (EphemeralEfakturaSubmission $row) use (&$latest) {
                $latest[$row->evolu_document_id] ??= $row;
            });

        return $latest;
    }

    public function refresh(User $user, string $evoluDocumentId, ?string $companyId = null): ?EphemeralEfakturaSubmission
    {
        $row = $this->latestForDocument($user, $evoluDocumentId, $companyId);
        if ($row !== null && isset($row->getAttribute('response_payload')['epostman'])) {
            app(EpostmanStatusSyncService::class)->refreshLocal($row);

            return $row->fresh();
        }
        if (! $row || $row->status !== ComplianceSubmissionStatus::Submitted) {
            return $row;
        }

        if (! $row->external_id) {
            return $row;
        }

        $bridgeCompany = $row->bridgeCompany()->first();
        if (! $bridgeCompany) {
            return $row;
        }

        $settings = CompanyEfakturaSettings::fromCompany($bridgeCompany);
        if (! $settings->configured()) {
            return $row;
        }

        $remotePayload = $this->fetchRemotePayload($settings, (string) $row->external_id);
        if ($remotePayload === null) {
            return $row;
        }

        $payload = array_merge(is_array($row->response_payload) ? $row->response_payload : [], $remotePayload);
        $mapped = SapiSkDocumentStatusMapper::fromProviderPayload($remotePayload);
        if ($mapped === null || $mapped === $row->status) {
            $row->update(['response_payload' => $payload]);

            return $row->fresh();
        }

        $row->update([
            'status' => $mapped,
            'response_payload' => $payload,
            'resolved_at' => in_array($mapped, [
                ComplianceSubmissionStatus::Approved,
                ComplianceSubmissionStatus::Rejected,
                ComplianceSubmissionStatus::Failed,
                ComplianceSubmissionStatus::Skipped,
            ], true) ? Carbon::now() : $row->resolved_at,
        ]);

        return $row->fresh();
    }

    protected function persistSubmission(
        User $user,
        Company $bridgeCompany,
        string $evoluDocumentId,
        ComplianceSubmissionResult $result,
    ): EphemeralEfakturaSubmission {
        $now = Carbon::now();
        $provider = ComplianceProvider::tryFrom($this->gateway->provider()) ?? ComplianceProvider::Peppol;

        return EphemeralEfakturaSubmission::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'evolu_document_id' => $evoluDocumentId,
                'provider' => $provider->value,
            ],
            [
                'bridge_company_id' => $bridgeCompany->id,
                'status' => $result->status,
                'external_id' => $result->externalId,
                'message' => $result->message,
                'response_payload' => $result->responsePayload,
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

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchRemotePayload(CompanyEfakturaSettings $settings, string $externalId): ?array
    {
        if ($this->client->sentDocumentDetailPathTemplate($settings->sapiBaseUrl()) === null) {
            return null;
        }

        try {
            $baseUrl = (string) $settings->sapiBaseUrl();
            $token = $this->client->accessToken(
                (string) $settings->sapiClientId(),
                (string) $settings->sapiClientSecret(),
                $baseUrl,
            );

            return $this->client->sentDocument(
                $token,
                (string) $settings->peppolParticipantId(),
                $externalId,
                $baseUrl,
            );
        } catch (RequestException $exception) {
            if (in_array($exception->response?->status(), [404, 405, 501], true)) {
                return null;
            }

            report($exception);

            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
