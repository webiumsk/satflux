<?php

namespace App\Services\Invoicing;

use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Models\EphemeralEfakturaSubmission;
use App\Models\IntegrationDocumentInbox;
use App\Models\User;
use App\Support\Invoicing\CompanyWiseSettings;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only projections of existing evidence. An item is resolved at its source,
 * never by dismissing it here. Category failures must not look like an empty inbox.
 *
 * @phpstan-type ActionItem array{id: string, kind: string, reference: string|null, document_id: string|null, occurred_at: string|null, amount: string|null, currency: string|null, bank_dedupe_hash?: string|null}
 * @phpstan-type SourceResult array{count: int, items: list<ActionItem>, state?: string}
 * @phpstan-type Category array{key: string, state: string, count: int|null, items: list<ActionItem>, has_more: bool}
 */
class CompanyActionCenterService
{
    private const MAX_ITEMS = 25;

    private const WISE_DUE_HOURS = 24;

    /** @return array{checked_at: string, categories: list<Category>, restricted_sources: list<string>} */
    public function forCompany(Company $company, User $user): array
    {
        // Do not trust a preloaded membership relation or an old caller snapshot.
        $company = Company::query()->findOrFail($company->id);
        abort_unless($company->isAccessibleBy($user), 403);
        $efakturaAllowed = $company->isOwnedBy($user) || $user->isAdmin() || $user->isSupport();

        return [
            'checked_at' => now()->toIso8601String(),
            'categories' => [
                $this->category('unmatched_payments', fn () => $this->unmatchedPayments($company)),
                $this->category('failed_sends', fn () => $this->failedSends($company, $efakturaAllowed)),
                $this->category('synchronization', fn () => $this->synchronization($company)),
                $this->category('incoming_documents', fn () => $this->incomingDocuments($company, $efakturaAllowed)),
            ],
            // Existing eFaktura inbox access is owner/support/admin only.
            'restricted_sources' => $efakturaAllowed ? [] : ['efaktura_owner_required'],
        ];
    }

    /**
     * @param  Closure(): SourceResult  $load
     * @return Category
     */
    private function category(string $key, Closure $load): array
    {
        try {
            $result = $load();
            $state = $result['state'] ?? 'ready';

            return [
                'key' => $key,
                'state' => $state,
                'count' => $state === 'ready' ? $result['count'] : null,
                'items' => $result['items'],
                'has_more' => $state === 'ready' && $result['count'] > count($result['items']),
            ];
        } catch (\Throwable) {
            // No raw SQL, provider error or customer payload enters this response.
            return ['key' => $key, 'state' => 'unavailable', 'count' => null, 'items' => [], 'has_more' => false];
        }
    }

    /** @return SourceResult */
    protected function unmatchedPayments(Company $company): array
    {
        $query = BankTransaction::query()
            ->where('company_id', $company->id)
            ->where('match_status', BankTransactionMatchStatus::Unmatched)
            ->excludingBalanceSnapshots()
            ->select(['id', 'booked_at', 'amount', 'currency', 'dedupe_hash']);

        return $this->queryResult($query, fn (BankTransaction $row) => array_merge($this->item(
            'bank:'.$row->id, 'unmatched_bank', occurredAt: $row->booked_at,
            amount: (string) $row->amount, currency: $row->currency,
        ), ['bank_dedupe_hash' => $row->dedupe_hash]), 'booked_at');
    }

    /** @return SourceResult */
    protected function failedSends(Company $company, bool $efakturaAllowed): array
    {
        $legacy = BusinessDocument::query()
            ->where('company_id', $company->id)
            ->whereNotNull('email_failed_at')
            ->where('status', '!=', BusinessDocumentStatus::Cancelled)
            ->select(['id', 'number', 'email_failed_at', 'total', 'currency']);
        $sources = [$this->queryResult($legacy, fn (BusinessDocument $row) => $this->item(
            'email:legacy:'.$row->id, 'legacy_email_failed', $row->number, $row->id,
            $row->email_failed_at, (string) $row->total, $row->currency,
        ), 'email_failed_at')];

        $integration = $this->integrationQuery($company)
            ->whereNotNull('payload_json->email_error')
            ->where('payload_json->email_error', '!=', '')
            ->whereNull('payload_json->emailed_at')
            ->select(['id', 'created_at', 'payload_json->number as document_number']);
        $sources[] = $this->queryResult($integration, fn (IntegrationDocumentInbox $row) => $this->item(
            'email:integration:'.$row->id, 'integration_email_failed',
            $this->reference($row->getAttribute('document_number')), occurredAt: $row->created_at,
        ));

        if ($efakturaAllowed) {
            $legacyEfaktura = BusinessDocumentCompliance::query()
                ->where('provider', ComplianceProvider::Peppol)
                ->whereIn('status', [ComplianceSubmissionStatus::Failed, ComplianceSubmissionStatus::Rejected])
                ->whereHas('document', fn (Builder $q) => $q->where('company_id', $company->id))
                ->with('document:id,number,total,currency')
                ->select(['id', 'business_document_id', 'updated_at']);
            $sources[] = $this->queryResult($legacyEfaktura, function (BusinessDocumentCompliance $row): array {
                $document = $row->document;
                if (! $document instanceof BusinessDocument) {
                    throw new \RuntimeException('Submission document is unavailable');
                }

                return $this->item(
                    'efaktura:legacy:'.$row->id, 'efaktura_send_failed', $document->number,
                    $row->business_document_id, $row->updated_at, (string) $document->total, $document->currency,
                );
            }, 'updated_at');

            $localEfaktura = EphemeralEfakturaSubmission::query()
                ->where('bridge_company_id', $company->id)
                ->where('user_id', $company->user_id)
                ->where('provider', 'peppol')
                ->whereIn('status', [ComplianceSubmissionStatus::Failed, ComplianceSubmissionStatus::Rejected])
                ->select(['id', 'evolu_document_id', 'updated_at']);
            $sources[] = $this->queryResult($localEfaktura, fn (EphemeralEfakturaSubmission $row) => $this->item(
                'efaktura:local:'.$row->id, 'efaktura_send_failed', documentId: $row->evolu_document_id,
                occurredAt: $row->updated_at,
            ), 'updated_at');
        }

        return $this->mergeSources($sources);
    }

    /** @return SourceResult */
    protected function synchronization(Company $company): array
    {
        $settings = CompanyWiseSettings::fromCompany($company);
        if (! $settings->configured()) {
            $stored = $settings->values;
            if (filled($stored['wise_api_token_encrypted'] ?? null) || filled($stored['wise_api_token'] ?? null)) {
                throw new \RuntimeException('Stored Wise credentials cannot be read');
            }

            return ['state' => 'not_configured', 'count' => 0, 'items' => []];
        }

        $lastSync = $settings->lastSyncAt();
        $at = $lastSync !== null ? Carbon::parse($lastSync) : null;
        if ($at !== null && $at->greaterThan(now()->addMinute())) {
            throw new \RuntimeException('Wise synchronization timestamp is not usable');
        }
        if ($at !== null && $at->greaterThan(now()->subHours(self::WISE_DUE_HOURS))) {
            return ['count' => 0, 'items' => []];
        }

        // Last success is evidence that a refresh may be due, not proof of failure.
        return ['count' => 1, 'items' => [$this->item(
            'sync:wise:'.$company->id, 'wise_sync_due', occurredAt: $at,
        )]];
    }

    /** @return SourceResult */
    protected function incomingDocuments(Company $company, bool $efakturaAllowed): array
    {
        $integration = $this->integrationQuery($company)
            ->select(['id', 'created_at', 'payload_json->number as document_number']);
        $sources = [$this->queryResult($integration, fn (IntegrationDocumentInbox $row) => $this->item(
            'incoming:integration:'.$row->id, 'integration_pending',
            $this->reference($row->getAttribute('document_number')), occurredAt: $row->created_at,
        ))];

        if ($efakturaAllowed) {
            $receipts = EfakturaInboundReceipt::query()
                ->where('company_id', $company->id)
                ->inboxPending()
                ->select(['id', 'external_number', 'created_at', 'total', 'currency']);
            $sources[] = $this->queryResult($receipts, fn (EfakturaInboundReceipt $row) => $this->item(
                'incoming:efaktura:'.$row->id, 'efaktura_pending', $row->external_number,
                occurredAt: $row->created_at, amount: $row->total !== null ? (string) $row->total : null,
                currency: $row->currency,
            ));
        }

        return $this->mergeSources($sources);
    }

    /** @return Builder<IntegrationDocumentInbox> */
    private function integrationQuery(Company $company): Builder
    {
        return IntegrationDocumentInbox::query()
            ->where('status', IntegrationDocumentInboxStatus::Pending)
            ->whereHas('storeIntegration', fn (Builder $integration) => $integration
                ->where('is_active', true)
                ->where('company_id', $company->id)
                ->whereHas('store', fn (Builder $store) => $store->where('company_id', $company->id)));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): ActionItem  $map
     * @return SourceResult
     */
    private function queryResult(Builder $query, Closure $map, string $dateColumn = 'created_at'): array
    {
        $count = (clone $query)->count();
        $items = (clone $query)->orderByDesc($dateColumn)->orderByDesc('id')
            ->limit(self::MAX_ITEMS)->get()->map($map)->values()->all();

        return ['count' => $count, 'items' => $items];
    }

    /**
     * @param  list<SourceResult>  $sources
     * @return SourceResult
     */
    private function mergeSources(array $sources): array
    {
        $items = [];
        $count = 0;
        foreach ($sources as $source) {
            $count += $source['count'];
            $items = array_merge($items, $source['items']);
        }
        usort($items, fn (array $a, array $b): int => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? '')
            ?: strcmp($a['id'], $b['id']));

        return ['count' => $count, 'items' => array_slice($items, 0, self::MAX_ITEMS)];
    }

    /** @return ActionItem */
    private function item(
        string $id,
        string $kind,
        ?string $reference = null,
        ?string $documentId = null,
        DateTimeInterface|string|null $occurredAt = null,
        ?string $amount = null,
        ?string $currency = null,
    ): array {
        $occurredAt = is_string($occurredAt) ? Carbon::parse($occurredAt) : $occurredAt;

        return [
            'id' => $id,
            'kind' => $kind,
            'reference' => $this->reference($reference),
            'document_id' => $documentId,
            'occurred_at' => $occurredAt?->format(DATE_ATOM),
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    private function reference(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = trim((string) $value);

        if ($value === '' || preg_match('/^[\[{]|[\x00-\x1f\x7f]/u', $value)) {
            return null;
        }

        return mb_substr($value, 0, 120);
    }
}
