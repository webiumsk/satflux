<?php

namespace App\Services\Invoicing\Efaktura;

use App\Enums\ComplianceProvider;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Models\EfakturaOutboundAttempt;
use App\Models\EphemeralEfakturaSubmission;
use App\Support\Invoicing\CompanyEfakturaSettings;
use App\Support\Invoicing\Efaktura\SapiSkSendPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Canonical provider observations; never upgrades SAPI acceptance to approval. */
class EpostmanStatusSyncService
{
    public function __construct(protected EpostmanEnterpriseClient $client) {}

    public function available(EfakturaOutboundAttempt $attempt, Company $company): bool
    {
        $settings = CompanyEfakturaSettings::fromCompany($company);

        return $company->user_id === $attempt->user_id && $settings->enterpriseConfigured()
            && $this->binding($attempt, $settings) !== null;
    }

    public function refreshServer(BusinessDocumentCompliance $row): void
    {
        $document = $row->document()->first();
        if (! $document instanceof BusinessDocument) {
            return;
        }
        $attempt = EfakturaOutboundAttempt::query()->where('company_id', $document->company_id)
            ->where('source_kind', 'server')->where('source_document_id', $document->id)
            ->where('external_id', $row->external_id)->first();
        if ($attempt !== null) {
            $this->refreshAttempt($attempt);
        }
    }

    public function refreshLocal(EphemeralEfakturaSubmission $row): void
    {
        $attempt = EfakturaOutboundAttempt::query()->where('company_id', $row->bridge_company_id)
            ->where('user_id', $row->user_id)->where('source_kind', 'local')
            ->where('source_document_id', $row->evolu_document_id)->where('external_id', $row->external_id)->first();
        if ($attempt !== null) {
            $this->refreshAttempt($attempt);
        }
    }

    public function refreshAttempt(EfakturaOutboundAttempt $snapshot, bool $manual = true): bool
    {
        if (! config('efaktura.enabled')) {
            return false;
        }
        $company = Company::query()->find($snapshot->company_id);
        if ($company === null || $company->user_id !== $snapshot->user_id) {
            return false;
        }
        $generation = CompanyEfakturaSettings::fromCompany($company)->enterpriseStatusFingerprint();

        return DB::transaction(function () use ($snapshot, $generation, $manual): bool {
            $company = Company::query()->whereKey($snapshot->company_id)->lockForUpdate()->first();
            if ($company === null || $company->user_id !== $snapshot->user_id) {
                return false;
            }
            $settings = CompanyEfakturaSettings::fromCompany($company);
            if (! $settings->enabled() || ! hash_equals($generation, $settings->enterpriseStatusFingerprint())) {
                return false;
            }
            $attempt = EfakturaOutboundAttempt::query()->whereKey($snapshot->id)->lockForUpdate()->first();
            if ($attempt === null || $attempt->company_id !== $company->id || $attempt->user_id !== $company->user_id
                || ! in_array($attempt->source_kind, ['server', 'local'], true)
                || $attempt->external_id === null || $attempt->external_id !== $snapshot->external_id
                || ($attempt->response_payload['epostman']['submission_state'] ?? null) !== 'accepted') {
                return false;
            }
            $cooldown = max(30, min(600, (int) config('efaktura.status_manual_cooldown_seconds', 60)));
            if (($manual && $attempt->status_last_attempt_at?->gt(now()->subSeconds($cooldown)))
                || (! $manual && ($attempt->status_complete || $attempt->status_next_check_at?->isFuture()))) {
                return false;
            }
            $projections = $this->projections($attempt);
            $binding = $this->binding($attempt, $settings);
            $result = ['observation' => null, 'error_code' => null];
            if ($binding === null) {
                $result['error_code'] = 'binding_unavailable';
            } elseif (! $settings->enterpriseConfigured()) {
                return false;
            } elseif ($projections->isEmpty()) {
                $result['error_code'] = 'projection_unavailable';
            } else {
                // Settings writes serialize on this company row for the whole GET.
                $result = $this->client->invoice((string) $settings->enterpriseOrigin(),
                    (string) $settings->enterpriseApiKey(), (string) $attempt->external_id, $binding);
            }
            $fresh = $company->fresh();
            if ($fresh === null || $fresh->user_id !== $snapshot->user_id
                || ! hash_equals($generation, CompanyEfakturaSettings::fromCompany($fresh)->enterpriseStatusFingerprint())
                || $this->projections($attempt)->modelKeys() !== $projections->modelKeys()) {
                return false;
            }
            $payload = $attempt->response_payload ?? [];
            $previous = $payload['epostman']['enterprise_status'] ?? [];
            $now = now();
            $observation = $result['observation'];
            $attempt->status_last_attempt_at = $now;
            $attempt->status_failures = $observation === null ? min(20, $attempt->status_failures + 1) : 0;
            $attempt->status_complete = $result['error_code'] === 'binding_unavailable'
                || ($observation !== null && $this->complete($observation));
            $delay = $observation === null ? min(86400, 300 * (2 ** min(8, $attempt->status_failures))) : 300;
            $attempt->status_next_check_at = $attempt->status_complete ? null : $now->copy()->addSeconds($delay);
            $payload['epostman']['delivery_status_available'] = $binding !== null && $settings->enterpriseConfigured();
            $payload['epostman']['enterprise_status'] = [
                'status' => $observation['status'] ?? $previous['status'] ?? null,
                'mls_c3_status' => $observation !== null ? $observation['mls_c3_status'] : ($previous['mls_c3_status'] ?? null),
                'mls_c5_status' => $observation !== null ? $observation['mls_c5_status'] : ($previous['mls_c5_status'] ?? null),
                'checked_at' => $observation !== null ? $now->toIso8601String() : ($previous['checked_at'] ?? null),
                'last_attempt_at' => $now->toIso8601String(),
                'next_check_at' => $attempt->status_next_check_at?->toIso8601String(),
                'error_code' => $result['error_code'],
            ];
            $attempt->response_payload = $payload;
            $attempt->save();
            foreach ($projections as $projection) {
                $projectionPayload = $projection->response_payload ?? [];
                $projectionPayload['epostman'] = array_replace($projectionPayload['epostman'] ?? [], $payload['epostman']);
                $projection->update(['response_payload' => $projectionPayload]);
            }

            return true;
        });
    }

    /** @return array{checked:int, updated:int} */
    public function syncDue(): array
    {
        $stats = ['checked' => 0, 'updated' => 0];
        if (! config('efaktura.enabled')) {
            return $stats;
        }
        $due = $this->due();
        $scanField = 'app_settings->efaktura_epostman_status_last_scan_at';
        $wrapped = DB::connection()->getQueryGrammar()->wrap($scanField);
        $companies = Company::query()->whereIn('id', (clone $due)->select('company_id'))
            ->whereNotNull('app_settings->efaktura_epostman_api_key_encrypted')
            ->orderByRaw($wrapped.' IS NOT NULL')->orderBy($scanField)->orderBy('id')
            ->limit(max(1, min(20, (int) config('efaktura.status_company_limit', 5))))->get();
        $limit = max(1, min(100, (int) config('efaktura.status_poll_limit', 20)));
        foreach ($companies as $company) {
            DB::transaction(function () use ($company): void {
                $locked = Company::query()->whereKey($company->id)->lockForUpdate()->first();
                if ($locked !== null && $locked->user_id === $company->user_id) {
                    $settings = $locked->app_settings ?? [];
                    $settings['efaktura_epostman_status_last_scan_at'] = now()->toIso8601String();
                    $locked->update(['app_settings' => $settings]);
                }
            });
            $attempts = $this->due()->where('company_id', $company->id)->orderByRaw('status_next_check_at IS NOT NULL')->orderBy('status_next_check_at')->orderBy('created_at')->orderBy('id')
                ->limit(min($limit - $stats['checked'], max(1, min(10, (int) config('efaktura.status_per_company_limit', 3)))))->get();
            foreach ($attempts as $attempt) {
                $stats['checked']++;
                $stats['updated'] += $this->refreshAttempt($attempt, manual: false) ? 1 : 0;
            }
            if ($stats['checked'] >= $limit) {
                break;
            }
        }

        return $stats;
    }

    /** @return Builder<EfakturaOutboundAttempt> */
    protected function due(): Builder
    {
        return EfakturaOutboundAttempt::query()->whereNotNull('external_id')->where('status_complete', false)
            ->where('response_payload->epostman->submission_state', 'accepted')
            ->where(fn ($query) => $query->whereNull('status_next_check_at')->orWhere('status_next_check_at', '<=', now()));
    }

    protected function projections(EfakturaOutboundAttempt $attempt): Collection
    {
        if ($attempt->source_kind === 'server') {
            return BusinessDocumentCompliance::query()->where('business_document_id', $attempt->source_document_id)
                ->where('external_id', $attempt->external_id)->where('provider', ComplianceProvider::Peppol)
                ->whereHas('document', fn ($q) => $q->where('company_id', $attempt->company_id))
                ->lockForUpdate()->get();
        }

        return EphemeralEfakturaSubmission::query()->where('user_id', $attempt->user_id)
            ->where('bridge_company_id', $attempt->company_id)->where('evolu_document_id', $attempt->source_document_id)
            ->where('provider', ComplianceProvider::Peppol)->where('external_id', $attempt->external_id)
            ->lockForUpdate()->get();
    }

    protected function binding(EfakturaOutboundAttempt $attempt, CompanyEfakturaSettings $settings): ?array
    {
        try {
            $binding = json_decode(Crypt::decryptString((string) $attempt->status_binding_encrypted), true, flags: JSON_THROW_ON_ERROR);
            $metadata = $binding['metadata'] ?? null;
            if (! is_array($metadata) || ($binding['origin'] ?? null) !== $settings->enterpriseOrigin()
                || ($binding['identity'] ?? null) !== $settings->enterpriseIdentityFingerprint()
                || ($metadata['documentId'] ?? null) !== $attempt->id
                || ($metadata['senderParticipantId'] ?? null) !== $settings->peppolParticipantId()) {
                return null;
            }
            if (preg_match('/^0245:[0-9]{10}$/D', (string) ($metadata['receiverParticipantId'] ?? '')) !== 1
                || ($metadata['processId'] ?? null) !== SapiSkSendPayload::PROCESS_BILLING
                || ! in_array($metadata['documentTypeId'] ?? null, [
                    'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1',
                    'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1',
                ], true)) {
                return null;
            }

            return $metadata;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function complete(array $observation): bool
    {
        if (in_array($observation['status'], ['VALIDATION_FAILED', 'REJECTED', 'CANCELLED', 'UNDELIVERABLE', 'FAILED'], true)) {
            return true;
        }

        $taxComplete = in_array($observation['mls_c5_status'], ['OK', 'AP', 'ACCEPTED', 'SUCCESS', 'PROCESSED'], true);
        if ($observation['status'] === 'DELIVERED_NON_PEPPOL') {
            // Domestic non-Peppol delivery has no AS4 recipient MLS (C3).
            return $taxComplete;
        }

        return in_array($observation['status'], ['DELIVERED', 'ACCEPTED'], true)
            && in_array($observation['mls_c3_status'], ['OK', 'AP', 'ACCEPTED', 'DELIVERED', 'SUCCESS', 'PROCESSED'], true)
            && $taxComplete;
    }
}
