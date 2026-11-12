<?php

namespace App\Services\Invoicing\Efaktura;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\ComplianceSubmissionStatus;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\EfakturaOutboundAttempt;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\ComplianceSubmissionResult;
use App\Support\Invoicing\Efaktura\PeppolParticipantId;
use App\Support\Invoicing\Efaktura\SapiSkSendPayload;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Manual ePostman sends: durable exact-body retry, never an automatic resend. */
class EpostmanOutboundSubmissionService
{
    public function __construct(protected SapiSkClient $client, protected BusinessDocumentUblService $ublService) {}

    public function assertIssuerMatches(Company $snapshot, Company $bridge): void
    {
        foreach (['registration_number', 'tax_id', 'vat_number', 'country'] as $field) {
            $left = strtoupper(preg_replace('/\s+/', '', (string) $snapshot->getAttribute($field)) ?? '');
            $right = strtoupper(preg_replace('/\s+/', '', (string) $bridge->getAttribute($field)) ?? '');
            if ($left !== $right || ($field === 'tax_id' && $left === '')) {
                $this->invalid('The invoice issuer does not match the connected company. Synchronize company settings first.');
            }
        }
        if ($snapshot->jurisdiction !== $bridge->jurisdiction || ! app(CompanyEfakturaEligibility::class)->supportsOutbound($snapshot)) {
            $this->invalid('The invoice issuer is not eligible for this connection.');
        }
    }

    public function submit(BusinessDocument $document): ComplianceSubmissionResult
    {
        if (DB::transactionLevel() !== 0) {
            $this->invalid('A durable submission cannot run inside an existing transaction.');
        }
        $snapshot = $document->company;
        if (! $snapshot instanceof Company) {
            $this->invalid('The invoice company is unavailable.');
        }
        $company = Company::query()->find($snapshot->id);
        if ($company === null || $company->user_id !== $snapshot->user_id) {
            $this->invalid('The connected company is unavailable.');
        }
        $this->assertIssuerMatches($snapshot, $company);
        $expected = CompanyEfakturaSettings::fromCompany($snapshot)->connectionFingerprint();
        $settings = CompanyEfakturaSettings::fromCompany($company);
        $this->assertConnection($company, $settings, $expected);

        // Keep the invoice's legal snapshot, but never accept snapshot credentials.
        $issuer = clone $snapshot;
        $issuer->setAttribute('app_settings', CompanyEfakturaSettings::withoutEnterpriseSecrets($company->getAttribute('app_settings') ?? []));
        $document->setRelation('company', $issuer);
        if ($document->issue_date === null) {
            $this->invalid('An issued invoice date is required for a stable submission.');
        }
        $receiver = $document->resolvedBuyer() !== null ? PeppolParticipantId::fromContact($document->resolvedBuyer()) : null;
        if ($receiver === null) {
            $this->invalid('The recipient Peppol participant ID is missing.');
        }
        $sender = (string) $settings->peppolParticipantId();
        $this->assertDomesticParticipants($document, $sender, $receiver);
        $this->assertSupportedAmounts($document);
        $xml = $this->ublService->xml($document, auditDownload: false);
        $prototype = SapiSkSendPayload::buildEpostman($document, $sender, $receiver, $xml, '00000000-0000-4000-8000-000000000000');
        $contentHash = hash('sha256', json_encode($prototype, JSON_THROW_ON_ERROR));
        $sourceKind = $document->exists ? 'server' : 'local';
        $sourceId = $document->exists ? (string) $document->id : (string) $document->getAttribute('ephemeral_evolu_document_id');
        if ($sourceId === '' || strlen($sourceId) > 64) {
            $this->invalid('A stable invoice identity is required.');
        }

        // This transaction MUST commit before the first provider call. A process
        // crash or lost response leaves the same request/key recoverable.
        $attempt = DB::transaction(function () use ($company, $issuer, $expected, $sourceKind, $sourceId, $contentHash, $prototype): EfakturaOutboundAttempt {
            $locked = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($locked, $company->user_id);
            $this->assertIssuerMatches($issuer, $locked);
            $this->assertConnection($locked, CompanyEfakturaSettings::fromCompany($locked), $expected);
            $this->assertServerDocumentCurrent($sourceKind, $sourceId, $locked, $contentHash);
            $existing = EfakturaOutboundAttempt::query()->where('user_id', $company->user_id)->where('source_kind', $sourceKind)->where('source_document_id', $sourceId)->lockForUpdate()->first();
            if ($existing !== null) {
                $this->assertAttempt($existing, $locked, $expected, $contentHash);

                return $existing;
            }
            $id = (string) Str::uuid();
            $body = $prototype;
            $body['metadata']['documentId'] = $id;

            return EfakturaOutboundAttempt::query()->create([
                'id' => $id, 'user_id' => $company->user_id, 'company_id' => $company->id,
                'source_kind' => $sourceKind, 'source_document_id' => $sourceId,
                'connection_fingerprint' => $expected, 'content_sha256' => $contentHash,
                'request_encrypted' => Crypt::encryptString(json_encode($body, JSON_THROW_ON_ERROR)),
                'status_binding_encrypted' => Crypt::encryptString(json_encode(['origin' => CompanyEfakturaSettings::fromCompany($locked)->enterpriseOrigin(), 'identity' => CompanyEfakturaSettings::fromCompany($locked)->enterpriseIdentityFingerprint(), 'metadata' => array_intersect_key($body['metadata'], array_flip(['documentId', 'senderParticipantId', 'receiverParticipantId', 'documentTypeId', 'processId']))], JSON_THROW_ON_ERROR)),
                'status' => ComplianceSubmissionStatus::Submitted,
                'response_payload' => ['epostman' => ['submission_state' => 'uncertain']],
                // Conservative margin below the provider's 24-hour retention.
                'retry_until' => now()->addHours(23)->addMinutes(50),
            ]);
        });

        return DB::transaction(function () use ($attempt, $company, $issuer, $expected, $contentHash, $sourceKind, $sourceId): ComplianceSubmissionResult {
            // Settings writes lock this same company row. A stale poll/send may
            // not cross a completed disable or credentials change.
            $locked = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($locked, $company->user_id);
            $this->assertIssuerMatches($issuer, $locked);
            $settings = CompanyEfakturaSettings::fromCompany($locked);
            $this->assertConnection($locked, $settings, $expected);
            $this->assertServerDocumentCurrent($sourceKind, $sourceId, $locked, $contentHash);
            $attempt = EfakturaOutboundAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->assertAttempt($attempt, $locked, $expected, $contentHash);
            if ($attempt->external_id !== null || ! $attempt->retry_until->isFuture()) {
                return $this->result($attempt);
            }

            $sendStarted = false;
            try {
                $body = json_decode(Crypt::decryptString($attempt->request_encrypted), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($body) || ($body['metadata']['documentId'] ?? null) !== $attempt->id) {
                    throw new \RuntimeException('Invalid retained submission.');
                }
                $retained = $body;
                $retained['metadata']['documentId'] = '00000000-0000-4000-8000-000000000000';
                if (! hash_equals($attempt->content_sha256, hash('sha256', json_encode($retained, JSON_THROW_ON_ERROR)))) {
                    throw new \RuntimeException('Invalid retained submission digest.');
                }
                $token = $this->client->accessToken((string) $settings->sapiClientId(), (string) $settings->sapiClientSecret(), $settings->sapiBaseUrl());
                $sendStarted = true;
                $response = $this->client->sendDocument($token, (string) $settings->peppolParticipantId(), $body, $attempt->id, $settings->sapiBaseUrl());
                $rejected = $response['status'] === 'REJECTED';
                $attempt->fill([
                    'status' => $rejected ? ComplianceSubmissionStatus::Rejected : ComplianceSubmissionStatus::Submitted,
                    'external_id' => $response['providerDocumentId'],
                    'request_encrypted' => null,
                    'response_payload' => ['epostman' => ['submission_state' => $rejected ? 'rejected' : 'accepted']],
                    'message' => $rejected ? 'ePostman rejected the submission. Check its portal.' : null,
                ]);
            } catch (\Throwable $exception) {
                // Never persist/report provider exception text, request XML or
                // response bodies. A timeout/5xx/malformed202 may follow acceptance.
                $httpStatus = $exception instanceof RequestException ? $exception->response->status() : null;
                $definite = ! $sendStarted || ($httpStatus !== null && $httpStatus >= 400 && $httpStatus < 500 && ! in_array($httpStatus, [408, 429], true));
                $attempt->fill([
                    'status' => $definite ? ComplianceSubmissionStatus::Failed : ComplianceSubmissionStatus::Submitted,
                    'response_payload' => ['epostman' => ['submission_state' => $definite ? 'rejected' : 'uncertain']],
                    'message' => $definite ? 'ePostman did not accept the request. Check the connection and provider portal.' : 'The submission result is unknown. Retry only this unchanged invoice within the displayed retry period, or check the provider portal.',
                ]);
            }
            $attempt->save();

            return $this->result($attempt);
        });
    }

    public function result(EfakturaOutboundAttempt $attempt): ComplianceSubmissionResult
    {
        $expired = ! $attempt->retry_until->isFuture();
        $payload = is_array($attempt->response_payload) ? $attempt->response_payload : [];
        $payload['epostman'] = array_merge($payload['epostman'] ?? [], [
            'retry_allowed' => $attempt->external_id === null && ! $expired,
            'retry_until' => $attempt->retry_until->toIso8601String(),
            'reconciliation_required' => $attempt->external_id === null && $expired,
            'delivery_status_available' => $attempt->status_binding_encrypted !== null
                && (($company = Company::query()->find($attempt->company_id)) !== null)
                && $company->user_id === $attempt->user_id
                && app(EpostmanStatusSyncService::class)->available($attempt, $company),
        ]);

        return new ComplianceSubmissionResult(status: $attempt->status, externalId: $attempt->external_id, responsePayload: $payload, message: $attempt->message);
    }

    /** Serialize legacy API projections with sends so an older timeout result
     * cannot overwrite a newer successful retry after the provider lock ends.
     *
     * @param  callable(ComplianceSubmissionResult): void  $persist
     */
    public function withLatestResult(BusinessDocument $document, ComplianceSubmissionResult $result, callable $persist): ComplianceSubmissionResult
    {
        if (! isset($result->responsePayload['epostman'])) {
            $persist($result);

            return $result;
        }

        $issuer = $document->company;
        if (! $issuer instanceof Company) {
            $this->invalid('The invoice company is unavailable.');
        }

        return DB::transaction(function () use ($document, $issuer, $persist): ComplianceSubmissionResult {
            $company = Company::query()->whereKey($issuer->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($company, $issuer->user_id);
            $attempt = EfakturaOutboundAttempt::query()->where('user_id', $company->user_id)
                ->where('company_id', $company->id)->where('source_kind', $document->exists ? 'server' : 'local')
                ->where('source_document_id', $document->exists ? $document->id : $document->getAttribute('ephemeral_evolu_document_id'))
                ->lockForUpdate()->firstOrFail();
            $current = $this->result($attempt);
            $persist($current);

            return $current;
        });
    }

    protected function assertAttempt(EfakturaOutboundAttempt $attempt, Company $company, string $fingerprint, string $hash): void
    {
        if ($attempt->company_id !== $company->id || $attempt->user_id !== $company->user_id || ! hash_equals($attempt->connection_fingerprint, $fingerprint) || ! hash_equals($attempt->content_sha256, $hash)) {
            $this->invalid('This invoice already has a submission with different content or connection. Reconcile it with the provider before making another submission.');
        }
    }

    protected function assertServerDocumentCurrent(string $kind, string $id, Company $company, string $contentHash): void
    {
        if ($kind !== 'server') {
            return;
        }
        $document = BusinessDocument::query()->whereKey($id)->where('company_id', $company->id)->lockForUpdate()->first();
        if ($document === null || $document->getAttribute('status') !== BusinessDocumentStatus::Issued || ! in_array($document->getAttribute('type'), [BusinessDocumentType::Invoice, BusinessDocumentType::CreditNote], true)) {
            $this->invalid('The invoice changed or is no longer issued. Reload it before sending.');
        }
        $document->setRelation('company', $company);
        $document->load(['contact', 'lines']);
        $this->assertSupportedAmounts($document);
        $receiver = $document->resolvedBuyer() !== null ? PeppolParticipantId::fromContact($document->resolvedBuyer()) : null;
        if ($receiver === null) {
            $this->invalid('The invoice recipient changed. Reload it before sending.');
        }
        $sender = (string) CompanyEfakturaSettings::fromCompany($company)->peppolParticipantId();
        $this->assertDomesticParticipants($document, $sender, $receiver);
        $body = SapiSkSendPayload::buildEpostman($document, $sender, $receiver, $this->ublService->xml($document, auditDownload: false), '00000000-0000-4000-8000-000000000000');
        if (! hash_equals($contentHash, hash('sha256', json_encode($body, JSON_THROW_ON_ERROR)))) {
            $this->invalid('The invoice content changed. Reload it before sending.');
        }
    }

    protected function assertConnection(Company $company, CompanyEfakturaSettings $settings, string $expected): void
    {
        if (! config('efaktura.enabled') || ! $settings->configured() || ! $this->client->isEpostman($settings->sapiBaseUrl()) || ! app(CompanyEfakturaEligibility::class)->supportsOutbound($company) || ! hash_equals($expected, $settings->connectionFingerprint())) {
            $this->invalid('The ePostman connection changed or is unavailable. Reload company settings before sending.');
        }
    }

    protected function assertOwner(Company $company, int $ownerId): void
    {
        if ($company->user_id !== $ownerId) {
            $this->invalid('The connected company ownership changed. Reload before sending.');
        }
    }

    /** The Slovak domestic CIUS requires both endpoints to use SK:DIC. */
    protected function assertDomesticParticipants(BusinessDocument $document, string $sender, string $receiver): void
    {
        $company = $document->company;
        $buyer = $document->resolvedBuyer();
        if (! $company instanceof Company || $buyer === null) {
            $this->invalid('The domestic invoice parties are unavailable.');
        }
        if (strtoupper(trim((string) $company->getAttribute('country'))) !== 'SK'
            || strtoupper(trim((string) $buyer->getAttribute('country'))) !== 'SK') {
            $this->invalid('Slovak ePostman sending requires both the sender and recipient country to be SK. Check the company and contact countries.');
        }

        foreach ([
            ['sender', $sender, (string) $company->getAttribute('tax_id')],
            ['recipient', $receiver, (string) $buyer->getAttribute('tax_id')],
        ] as [$party, $participant, $taxId]) {
            // Normalize formatting only; never invent a DIČ from a VAT number,
            // another participant scheme or a registration identifier.
            $normalized = preg_replace('/\s+/', '', trim($taxId)) ?? '';
            if (! preg_match('/^[0-9]{10}$/D', $normalized) || $participant !== '0245:'.$normalized) {
                $this->invalid('Slovak ePostman sending requires the '.$party.' participant to be 0245 followed by that party\'s ten-digit DIČ. Check the company and contact tax identifiers.');
            }
        }
    }

    protected function assertSupportedAmounts(BusinessDocument $document): void
    {
        if ((float) $document->getAttribute('discount_percent') !== 0.0 || $document->lines->contains(fn ($line): bool => (float) $line->getAttribute('line_discount_percent') !== 0.0)) {
            $this->invalid('ePostman sending does not yet support invoice or line discounts.');
        }
        foreach ($document->lines as $line) {
            foreach (['unit_price' => 2, 'quantity' => 4, 'tax_rate' => 2] as $field => $precision) {
                $raw = (float) ($line->getAttributes()[$field] ?? 0);
                $cast = (float) $line->getAttribute($field);
                if ($field === 'unit_price' && $raw < 0) {
                    $this->invalid('ePostman sending does not yet support negative unit prices.');
                }
                if (! is_finite($raw) || abs($raw - $cast) > 0.000000001) {
                    $this->invalid("ePostman sending currently supports {$field} with at most {$precision} decimal places.");
                }
            }
        }
        $canonical = app(CanonicalInvoiceBuilder::class)->fromDocument($document);
        if ($canonical->subtotal !== (string) $document->getAttribute('subtotal') || $canonical->taxTotal !== (string) $document->getAttribute('tax_total') || $canonical->total !== (string) $document->getAttribute('total')) {
            $this->invalid('The e-invoice amounts do not match the issued invoice. Reconcile the invoice before sending.');
        }
        $lines = $document->lines->keyBy('sort_order');
        foreach ($canonical->lines as $line) {
            $source = $lines->get($line->sortOrder);
            if ($source === null || $line->grossAmount !== (string) $source->getAttribute('line_total')) {
                $this->invalid('The e-invoice line amounts do not match the issued invoice.');
            }
        }
        $paid = (float) $document->getAttribute('amount_paid');
        if (! is_finite($paid) || $paid < 0 || $paid > (float) $canonical->total) {
            $this->invalid('The paid amount must be between zero and the issued invoice total.');
        }
    }

    protected function invalid(string $message): never
    {
        throw ValidationException::withMessages(['efaktura' => [$message]]);
    }
}
