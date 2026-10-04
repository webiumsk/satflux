<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\CompanyDocumentSequence;
use App\Models\DocumentNumberReservation;
use App\Models\EphemeralEfakturaSubmission;
use App\Support\LandingCopy;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentSequenceService
{
    public function __construct(
        protected DocumentNumberFormatter $formatter,
    ) {}

    public function nextNumber(Company $company, string $documentType, ?int $localHighCounter = null): string
    {
        return DB::transaction(function () use ($company, $documentType, $localHighCounter) {
            $series = $this->resolveSeriesForIssue($company, $documentType);

            $series = CompanyDocumentSequence::query()
                ->where('id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            $date = now();
            // The reservation floor matters here too: a company that issues
            // through the local-first allocator has no server documents, so
            // without it this path handed out numbers already reserved.
            $this->alignCounter($series, $localHighCounter, $date);

            $series->last_number = (int) $series->last_number + 1;
            $series->save();

            return $this->formatter->format(
                $series->format,
                (int) $series->last_number,
                $date,
            );
        });
    }

    /**
     * Atomically reserves the next number for an issue attempt (audit F3).
     *
     * Idempotent per (company, document type, issue_request_id): a retried
     * request returns the existing reservation - including its number - in
     * whatever status it currently has, so a client can recover an
     * interrupted issue without burning another number.
     *
     * $periodDate is the client's LOCAL calendar date: the server runs in
     * UTC, so in the first hours of a year the client already formats the
     * new year while the server period is still the old one. It is honoured
     * only within one day of the server date.
     */
    public function reserveNumberForIssue(
        Company $company,
        string $documentType,
        string $issueRequestId,
        ?int $localHighCounter = null,
        ?int $reservedByUserId = null,
        ?CarbonInterface $periodDate = null,
    ): DocumentNumberReservation {
        $date = $this->resolvePeriodDate($periodDate);

        try {
            return DB::transaction(function () use ($company, $documentType, $issueRequestId, $localHighCounter, $reservedByUserId, $date) {
                $existing = DocumentNumberReservation::query()
                    ->where('company_id', $company->id)
                    ->where('document_type', $documentType)
                    ->where('issue_request_id', $issueRequestId)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }

                $series = $this->resolveSeriesForIssue($company, $documentType);
                $series = CompanyDocumentSequence::query()
                    ->where('id', $series->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Server documents, the client's local high counter and the
                // reservations of this period - never hand out a number at or
                // below an existing reservation (local-first clients create no
                // server documents). Voided reservations count too.
                $this->alignCounter($series, $localHighCounter, $date);

                $series->last_number = (int) $series->last_number + 1;
                $series->save();

                $counter = (int) $series->last_number;

                return DocumentNumberReservation::create([
                    'company_id' => $company->id,
                    'document_type' => $documentType,
                    'company_document_sequence_id' => $series->id,
                    'issue_request_id' => $issueRequestId,
                    'period_key' => $series->period_key,
                    'counter' => $counter,
                    'number' => $this->formatter->format($series->format, $counter, $date),
                    'status' => DocumentNumberReservation::STATUS_RESERVED,
                    'reserved_by_user_id' => $reservedByUserId,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two first attempts of the SAME issue request raced: locking a
            // row that does not exist yet locks nothing, so both inserted.
            // The loser returns the winner's reservation (idempotency).
            $existing = $this->findReservation($company, $documentType, $issueRequestId);
            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Marks a reservation confirmed once the client has persisted the issued
     * snapshot. Stores only an opaque hash + format version - never content.
     * Idempotent for already confirmed reservations; refuses voided ones.
     */
    public function confirmReservation(
        Company $company,
        string $documentType,
        string $issueRequestId,
        ?string $snapshotHash = null,
        ?string $snapshotFormatVersion = null,
    ): DocumentNumberReservation {
        return DB::transaction(function () use ($company, $documentType, $issueRequestId, $snapshotHash, $snapshotFormatVersion) {
            $reservation = $this->lockedReservation($company, $documentType, $issueRequestId);

            if ($reservation->status === DocumentNumberReservation::STATUS_VOIDED) {
                throw ValidationException::withMessages([
                    'issue_request_id' => ['Reservation was voided and cannot be confirmed.'],
                ]);
            }

            if ($reservation->status !== DocumentNumberReservation::STATUS_CONFIRMED) {
                $reservation->status = DocumentNumberReservation::STATUS_CONFIRMED;
                $reservation->confirmed_hash = $snapshotHash;
                $reservation->confirmed_format_version = $snapshotFormatVersion;
                $reservation->save();
            }

            return $reservation;
        });
    }

    /**
     * Voids an unconfirmed reservation (client abandoned the issue). The
     * number is NOT recycled - the sequence keeps its gap. Idempotent for
     * already voided reservations; refuses confirmed ones.
     */
    public function voidReservation(
        Company $company,
        string $documentType,
        string $issueRequestId,
    ): DocumentNumberReservation {
        return DB::transaction(function () use ($company, $documentType, $issueRequestId) {
            $reservation = $this->lockedReservation($company, $documentType, $issueRequestId);

            if ($reservation->status === DocumentNumberReservation::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'issue_request_id' => ['Reservation was confirmed and cannot be voided.'],
                ]);
            }

            if ($reservation->status !== DocumentNumberReservation::STATUS_VOIDED) {
                $reservation->status = DocumentNumberReservation::STATUS_VOIDED;
                $reservation->save();
            }

            return $reservation;
        });
    }

    /**
     * Releases the reservation holding a deleted invoice's number so the
     * sequence stays GAPLESS (P3 numbering rework, user requirement: deleted
     * numbers must be reissued). Only the HIGHEST counter of the series
     * period can be released - deleting older invoices is refused upstream,
     * and this guard makes the rule race-safe. Chained deletes (newest
     * first) release one number at a time.
     *
     * The reservation is addressed by its issue_request_id (the local-first
     * document id) when given: the client renders the number with its LOCAL
     * series format, which may differ from the server series format stored
     * on the reservation, so a by-number lookup silently missed and the
     * reservation floor kept the deleted number burned. The number lookup
     * (current period of the default series) remains for documents whose
     * reservation key the client does not know (imported / auto-issued).
     *
     * Returns released=false with reason "not_found" when no reservation
     * holds the number (pre-allocator documents) - a harmless no-op.
     *
     * @return array{released: bool, reason?: string}
     */
    public function releaseReservation(
        Company $company,
        string $documentType,
        ?string $issueRequestId,
        ?string $number,
    ): array {
        return DB::transaction(function () use ($company, $documentType, $issueRequestId, $number) {
            // A local-first document sent to e-Faktura is on record at the
            // tax authority under this number - releasing it would hand the
            // same number to the next invoice. Mirrors the server-document
            // rule (BusinessDocument::hasBlockingRelations).
            if ($issueRequestId !== null && $issueRequestId !== ''
                && EphemeralEfakturaSubmission::query()
                    ->where('bridge_company_id', $company->id)
                    ->where('evolu_document_id', $issueRequestId)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'efaktura' => ['This document was submitted to e-Faktura - its number cannot be released.'],
                ]);
            }

            $candidate = null;
            if ($issueRequestId !== null && $issueRequestId !== '') {
                $candidate = $this->findReservation($company, $documentType, $issueRequestId);
            }

            if (! $candidate && $number !== null && $number !== '') {
                $default = $this->resolveSeriesForIssue($company, $documentType);
                $periodKey = $this->currentPeriodKey($default->reset_period);
                $candidate = DocumentNumberReservation::query()
                    ->where('company_document_sequence_id', $default->id)
                    ->where('period_key', $periodKey)
                    ->where('number', $number)
                    ->first();
            }

            if (! $candidate) {
                return ['released' => false, 'reason' => 'not_found'];
            }

            // Series first, then the reservation - the same lock order as
            // the reserve path.
            $series = CompanyDocumentSequence::query()
                ->where('id', $candidate->company_document_sequence_id)
                ->lockForUpdate()
                ->firstOrFail();
            $reservation = DocumentNumberReservation::query()
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->first();
            if (! $reservation) {
                return ['released' => false, 'reason' => 'not_found'];
            }

            $scoped = DocumentNumberReservation::query()
                ->where('company_document_sequence_id', $series->id)
                ->when(
                    $reservation->period_key === null,
                    fn ($query) => $query->whereNull('period_key'),
                    fn ($query) => $query->where('period_key', $reservation->period_key),
                );

            $maxCounter = (int) (clone $scoped)->max('counter');
            if ((int) $reservation->counter !== $maxCounter) {
                throw ValidationException::withMessages([
                    'number' => ['Only the most recent number of the series can be released.'],
                ]);
            }

            $reservation->delete();

            // Explicit lowering (the stored counter is otherwise a floor):
            // back to just below the released number - not to the remaining
            // reservations, which would also drop a manual start value.
            $lowered = max((int) (clone $scoped)->max('counter'), (int) $reservation->counter - 1);
            if ($series->period_key === $reservation->period_key
                && (int) $series->last_number > $lowered) {
                $series->last_number = $lowered;
                $series->save();
            }

            AuditLog::log('company.document_number_released', 'company', $company->id, [
                'document_type' => $documentType,
                'number' => $reservation->number,
                'counter' => (int) $reservation->counter,
            ]);

            return ['released' => true];
        });
    }

    public function findReservation(
        Company $company,
        string $documentType,
        string $issueRequestId,
    ): ?DocumentNumberReservation {
        return DocumentNumberReservation::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('issue_request_id', $issueRequestId)
            ->first();
    }

    /**
     * Highest counter ever reserved for the series' current period. Safe
     * against races: every reservation for a series runs under the same
     * lockForUpdate on the series row.
     */
    protected function reservedCounterFloor(CompanyDocumentSequence $series): int
    {
        return (int) DocumentNumberReservation::query()
            ->where('company_document_sequence_id', $series->id)
            ->when(
                $series->period_key === null,
                fn ($query) => $query->whereNull('period_key'),
                fn ($query) => $query->where('period_key', $series->period_key),
            )
            ->max('counter');
    }

    protected function lockedReservation(
        Company $company,
        string $documentType,
        string $issueRequestId,
    ): DocumentNumberReservation {
        $reservation = DocumentNumberReservation::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('issue_request_id', $issueRequestId)
            ->lockForUpdate()
            ->first();

        if (! $reservation) {
            throw ValidationException::withMessages([
                'issue_request_id' => ['No reservation found for this issue request.'],
            ]);
        }

        return $reservation;
    }

    /**
     * Next counter WITHOUT allocating it. Read-only: previews used to save
     * a recomputed counter from an unlocked GET, racing the reserve path.
     */
    public function previewNextCounter(Company $company, string $documentType, ?int $localHighCounter = null): int
    {
        $series = $this->resolveSeriesForIssue($company, $documentType);
        $this->alignCounter($series, $localHighCounter, now());

        return (int) $series->last_number + 1;
    }

    public function previewNext(CompanyDocumentSequence $series, ?int $counterOverride = null): string
    {
        $date = now();
        if ($counterOverride === null) {
            $series = clone $series;
            $this->alignCounter($series, null, $date);
            $counterOverride = (int) $series->last_number + 1;
        }

        return $this->formatter->format($series->format, $counterOverride, $date);
    }

    public function previewNextNumber(Company $company, string $documentType, ?int $localHighCounter = null): string
    {
        return $this->formatter->format(
            $this->resolveSeriesForIssue($company, $documentType)->format,
            $this->previewNextCounter($company, $documentType, $localHighCounter),
        );
    }

    /**
     * Recalculate the default series counter after documents were deleted.
     * The deleted numbers are the explicit lowering path of a gapless
     * delete: the counter drops to just below the smallest of them (never
     * below what the remaining documents and reservations use). Without
     * them the counter only rises.
     *
     * @param  array<int, string>  $deletedNumbers
     */
    public function syncSeriesAfterDocumentChange(Company $company, string $documentType, array $deletedNumbers = []): void
    {
        DB::transaction(function () use ($company, $documentType, $deletedNumbers) {
            $series = CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $documentType)
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if (! $series) {
                return;
            }

            $date = now();
            $released = [];
            foreach ($deletedNumbers as $number) {
                $counter = $this->formatter->counterInPeriod(
                    (string) $series->format,
                    $number,
                    (string) $series->reset_period,
                    $date,
                );
                if ($counter !== null) {
                    $released[] = $counter;
                }
            }

            $this->alignCounter($series, null, $date, $released !== [] ? min($released) - 1 : null);
            $series->save();
        });
    }

    /**
     * Brings the (in-memory) series counter to the highest number used in
     * the CURRENT period: server documents of that period, the local-first
     * client's high counter (only meaningful for the same period), every
     * reservation of the period and the stored counter itself - the "last
     * used" value a migrating user types into the series panel is a floor
     * (syncPeriod() resets it to 0 in a new period). It is lowered only
     * through $lowerTo, the explicit gapless-delete path. Callers that
     * allocate save it under the series row lock; previews never save.
     */
    protected function alignCounter(
        CompanyDocumentSequence $series,
        ?int $localHighCounter,
        CarbonInterface $date,
        ?int $lowerTo = null,
    ): void {
        $this->syncPeriod($series, $date);

        $stored = (int) $series->last_number;
        $floor = $lowerTo !== null ? min($stored, max(0, $lowerTo)) : $stored;

        $counter = $this->highestUsedCounterInPeriod($series, $date);
        if ($localHighCounter !== null && $localHighCounter > $counter) {
            $counter = $localHighCounter;
        }

        $series->last_number = max($counter, $floor, $this->reservedCounterFloor($series));
    }

    /**
     * Highest counter among server documents numbered in this series'
     * format AND period. Numbers of earlier years (yearly reset), other
     * formats or foreign imports do not count.
     */
    protected function highestUsedCounterInPeriod(CompanyDocumentSequence $series, CarbonInterface $date): int
    {
        $rows = $series->document_type === 'expense'
            ? BusinessExpense::query()
                ->where('company_id', $series->company_id)
                ->whereNotNull('internal_number')
                ->get(['internal_number as number', 'issue_date'])
            : BusinessDocument::query()
                ->where('company_id', $series->company_id)
                ->where('type', $series->document_type)
                ->whereNotNull('number')
                ->get(['number', 'issue_date']);

        $max = 0;
        foreach ($rows as $row) {
            $counter = $this->formatter->counterInPeriod(
                (string) $series->format,
                (string) $row->number,
                (string) $series->reset_period,
                $date,
                $row->issue_date ? Carbon::parse($row->issue_date) : null,
            );
            if ($counter !== null && $counter > $max) {
                $max = $counter;
            }
        }

        return $max;
    }

    /**
     * A local high counter observed at $observedAt (the synced auto-issue
     * profile) belongs to the period it was observed in. Applied in a later
     * period it carried last year's count into the new year.
     */
    public function localHighCounterForCurrentPeriod(
        Company $company,
        string $documentType,
        ?int $counter,
        ?CarbonInterface $observedAt,
    ): ?int {
        if ($counter === null || $observedAt === null) {
            return null;
        }

        $resetPeriod = (string) $this->resolveSeriesForIssue($company, $documentType)->reset_period;

        return $this->currentPeriodKey($resetPeriod, $observedAt) === $this->currentPeriodKey($resetPeriod)
            ? $counter
            : null;
    }

    /**
     * Memo of latestNumberedDocumentId() per company + type. The service is
     * bound "scoped" (one instance per request / queue job) and every
     * BusinessDocument save or delete clears it, so a bulk delete or a
     * batch of serialized documents (can_delete is appended) does not
     * re-scan the series each time.
     *
     * @var array<string, string|null>
     */
    protected array $latestNumberedCache = [];

    public function forgetLatestNumbered(): void
    {
        $this->latestNumberedCache = [];
    }

    /**
     * Id of the document holding the top number of its type - the only
     * numbered document that may be deleted (gapless numbering). Ordered by
     * the number parsed with the default series format (year, month,
     * counter) - not by created_at: drafts are issued in any order and the
     * issue date of an issued document can be edited, so the candidates
     * cannot be narrowed by date in SQL without breaking that rule. The
     * scan reads four plain columns (no model hydration) and is memoized.
     */
    public function latestNumberedDocumentId(string $companyId, string $documentType): ?string
    {
        $key = $companyId.'|'.$documentType;
        if (array_key_exists($key, $this->latestNumberedCache)) {
            return $this->latestNumberedCache[$key];
        }

        $series = CompanyDocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->orderBy('id')
            ->first(['format', 'reset_period']);
        $format = (string) $series?->format;
        $resetPeriod = (string) ($series->reset_period ?? 'yearly');

        $latestId = null;
        $latestKey = null;
        $rows = BusinessDocument::query()
            ->where('company_id', $companyId)
            ->where('type', $documentType)
            ->whereNotNull('number')
            ->toBase()
            ->get(['id', 'number', 'issue_date', 'created_at']);
        foreach ($rows as $row) {
            $sortKey = $this->sortKeyFor(
                $format,
                $resetPeriod,
                (string) $row->number,
                $row->issue_date !== null ? Carbon::parse($row->issue_date) : null,
                $row->created_at !== null ? Carbon::parse($row->created_at) : null,
            ).(string) $row->id;
            if ($latestKey === null || strcmp($sortKey, $latestKey) > 0) {
                $latestKey = $sortKey;
                $latestId = (string) $row->id;
            }
        }

        return $this->latestNumberedCache[$key] = $latestId;
    }

    /** Sortable "position in the sequence" of a numbered document. */
    public function numberSortKey(string $format, BusinessDocument $document, string $resetPeriod = 'yearly'): string
    {
        return $this->sortKeyFor(
            $format,
            $resetPeriod,
            (string) $document->number,
            $document->issue_date ? Carbon::parse($document->issue_date) : null,
            $document->created_at,
        );
    }

    protected function sortKeyFor(
        string $format,
        string $resetPeriod,
        string $number,
        ?CarbonInterface $issueDate,
        ?CarbonInterface $createdAt,
    ): string {
        $parsed = $format !== '' ? $this->formatter->parse($format, $number) : null;

        if ($resetPeriod === 'never') {
            // The counter never restarts, so it alone orders the sequence -
            // an (editable) issue date must not lift a lower number above a
            // higher one across years, e.g. in a counter-only FVNNNN series.
            $year = 0;
            $month = 0;
        } else {
            $year = $parsed['year'] ?? null;
            $year = $year !== null ? (strlen($year) <= 2 ? 2000 + (int) $year : (int) $year) : ($issueDate !== null ? $issueDate->year : 0);
            $month = $parsed['month'] ?? null;
            $month = $month !== null ? (int) $month : 0;
        }

        // A number outside the series format (imported, older format) falls
        // back to its trailing digits.
        $counter = $parsed['counter']
            ?? (preg_match('/(\d{1,12})$/', $number, $m) ? (int) $m[1] : 0);

        return sprintf(
            '%04d%02d%d%012d%s%012d',
            $year,
            $month,
            $parsed !== null ? 1 : 0,
            $counter,
            $issueDate?->format('Ymd') ?? '00000000',
            $createdAt?->getTimestamp() ?? 0,
        );
    }

    public function currentPeriodKey(string $resetPeriod, ?CarbonInterface $date = null): string
    {
        $date ??= now();

        return match ($resetPeriod) {
            'monthly' => $date->format('Y-m'),
            'never' => 'all',
            default => $date->format('Y'),
        };
    }

    /**
     * The client's local date is trusted for the period only within one day
     * of the server's (UTC) date - enough for every timezone, useless for
     * back- or forward-dating a number into another year.
     */
    protected function resolvePeriodDate(?CarbonInterface $periodDate): CarbonInterface
    {
        $now = now();
        if ($periodDate === null) {
            return $now;
        }

        $days = abs($now->copy()->startOfDay()->diffInDays($periodDate->copy()->startOfDay(), false));

        return $days <= 1 ? $periodDate->copy()->setTimeFrom($now) : $now;
    }

    /**
     * Default number series seeded for new companies.
     * Format tokens: Y = year, M = month, N = counter (run of 2+). Legacy R/C still work.
     * Single Y or N are literal characters. Literal prefix letters must not use token runs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultSeriesDefinitions(?string $locale = null): array
    {
        return array_map(function (array $def) use ($locale) {
            return [
                'document_type' => $def['document_type'],
                'name' => LandingCopy::get($def['name_key'], $locale),
                'format' => $def['format'],
                'is_default' => true,
            ];
        }, $this->defaultSeriesFormatDefinitions());
    }

    /**
     * @return array<int, array{document_type: string, name_key: string, format: string}>
     */
    protected function defaultSeriesFormatDefinitions(): array
    {
        return [
            ['document_type' => 'invoice', 'name_key' => 'invoicing.series_default_name_invoice', 'format' => 'INVYYYYNNNN'],
            ['document_type' => 'credit_note', 'name_key' => 'invoicing.series_default_name_credit_note', 'format' => 'CNYYYYNNNN'],
            ['document_type' => 'proforma', 'name_key' => 'invoicing.series_default_name_proforma', 'format' => 'PFYYYYNNNN'],
            ['document_type' => 'delivery_note', 'name_key' => 'invoicing.series_default_name_delivery_note', 'format' => 'DELYYYYNNNN'],
            ['document_type' => 'quote', 'name_key' => 'invoicing.series_default_name_quote', 'format' => 'QTYYYYNNNN'],
            ['document_type' => 'order_received', 'name_key' => 'invoicing.series_default_name_order_received', 'format' => 'POYYYYNNNN'],
            ['document_type' => 'order_issued', 'name_key' => 'invoicing.series_default_name_order_issued', 'format' => 'SOYYYYNNNN'],
            ['document_type' => 'expense', 'name_key' => 'invoicing.series_default_name_expense', 'format' => 'EXPYYYYNNNN'],
        ];
    }

    public function seedDefaultsForCompany(Company $company, ?string $locale = null): void
    {
        foreach ($this->defaultSeriesDefinitions($locale) as $def) {
            $exists = CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $def['document_type'])
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            CompanyDocumentSequence::create([
                'company_id' => $company->id,
                'document_type' => $def['document_type'],
                'name' => $def['name'],
                'format' => $def['format'],
                'reset_period' => 'yearly',
                'is_default' => $def['is_default'],
                'period_key' => $this->currentPeriodKey('yearly'),
                'last_number' => 0,
            ]);
        }
    }

    protected function resolveSeriesForIssue(Company $company, string $documentType): CompanyDocumentSequence
    {
        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if ($series) {
            return $series;
        }

        $this->seedDefaultsForCompany($company);

        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if (! $series) {
            throw ValidationException::withMessages([
                'number_series' => ['No default number series found for this document type.'],
            ]);
        }

        return $series;
    }

    protected function syncPeriod(CompanyDocumentSequence $series, ?CarbonInterface $date = null): void
    {
        $key = $this->currentPeriodKey($series->reset_period, $date);
        if ($series->period_key !== $key) {
            $series->period_key = $key;
            $series->last_number = 0;
        }
    }
}
