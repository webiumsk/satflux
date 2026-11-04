<?php

namespace App\Services\Invoicing;

use App\Models\BankTransaction;
use App\Models\Company;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/** Alternating head/history scans retain admitted incomplete-page state in authenticated bookmarks. */
final class BankEmailSyncFeed
{
    private const ORDER = 'created_at:null-last:desc,id:desc';

    /**
     * Fetch one bounded lane without counting or summarizing the company's history.
     * Candidate frames propose scan progress after ACK or admitted-incomplete outcomes.
     * Only a clean full scan cycle clears sticky unresolved state; cursors do not prove global coverage.
     *
     * @param  array<string, mixed>  $input
     * @return array{rows: Collection<int, BankTransaction>, sync: array{protocol: int, lane: string, next_cursor: string, retry_cursor: string, cycle_complete: bool, unresolved: bool, unresolved_after_success: bool}}
     */
    public function page(Company $company, string $userId, array $input): array
    {
        $validator = Validator::make($input, [
            'source' => ['required', 'string', 'in:email'],
            'sync_context' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sync_cursor' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ]);
        if ($validator->fails()
            || array_diff(array_keys($input), ['sync', 'source', 'sync_context', 'sync_cursor', 'per_page']) !== []) {
            $this->invalidCursor();
        }
        $validated = $validator->validated();
        $pageSize = (int) ($validated['per_page'] ?? 100);
        $binding = [
            'protocol' => 2,
            'user_id' => $userId,
            'company_id' => (string) $company->getKey(),
            'source' => 'email',
            'context' => (string) $validated['sync_context'],
            'page_size' => $pageSize,
            'order' => self::ORDER,
        ];
        $frame = $this->readFrame($validated['sync_cursor'] ?? null, $binding);
        $lane = $frame['next_lane'];
        $before = $frame['backfill_before'];

        $query = BankTransaction::query()
            ->where('company_id', $company->getKey())
            ->where('source', 'email')
            ->excludingBalanceSnapshots()
            ->with([
                'match.document:id,number,status,total,currency,type',
                'expense:id,internal_number,title,status,total,currency',
            ]);
        $found = $this->readOrderedPage($query, $pageSize + 1, $lane === 'backfill' ? $before : null);
        $hasMore = $found->count() > $pageSize;
        $rows = $found->take($pageSize)->values();
        $last = $rows->last();
        $lastPosition = $last === null ? null : $this->position($last);

        if ($lane === 'head') {
            // A successful head read can seed a new cycle, but cannot jump an
            // existing history frontier when newer rows arrive above it.
            $wraps = $before === null && ! $hasMore;
            $nextBefore = $wraps ? null : ($before ?? $lastPosition);
            $nextLane = $wraps ? 'head' : 'backfill';
        } else {
            $wraps = ! $hasMore;
            $nextBefore = $wraps ? null : $lastPosition;
            $nextLane = 'head';
        }
        $nextIncomplete = $wraps ? false : $frame['cycle_incomplete'];
        $nextUnresolved = $wraps ? $frame['cycle_incomplete'] : $frame['unresolved'];

        return [
            'rows' => $rows,
            'sync' => [
                'protocol' => 2,
                'lane' => $lane,
                'next_cursor' => $this->seal($binding, $nextLane, $nextBefore, $nextIncomplete, $nextUnresolved),
                // An admitted incomplete page advances its scan position while
                // retaining unresolved evidence. Unknown/unadmitted outcomes use neither frame.
                'retry_cursor' => $this->seal($binding, $lane === 'head' ? 'backfill' : 'head', $nextBefore, ! $wraps, true),
                'cycle_complete' => $wraps && ! $frame['cycle_incomplete'],
                'unresolved' => $frame['unresolved'],
                'unresolved_after_success' => $nextUnresolved,
            ],
        ];
    }

    /**
     * Authenticate one complete cursor frame and reject every binding/schema mismatch uniformly.
     * Cursors carry no expiry or authorization; the route still enforces the current company owner.
     *
     * @param  array<string, int|string>  $binding
     * @return array{next_lane: string, backfill_before: array{created_at: ?string, id: string}|null, cycle_incomplete: bool, unresolved: bool}
     */
    private function readFrame(?string $cursor, array $binding): array
    {
        if ($cursor === null) {
            return ['next_lane' => 'head', 'backfill_before' => null, 'cycle_incomplete' => false, 'unresolved' => false];
        }
        try {
            $frame = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->invalidCursor();
        }
        $expectedKeys = [...array_keys($binding), 'next_lane', 'backfill_before', 'cycle_incomplete', 'unresolved'];
        if (! is_array($frame) || count($frame) !== count($expectedKeys)
            || array_diff(array_keys($frame), $expectedKeys) !== []) {
            $this->invalidCursor();
        }
        foreach ($binding as $key => $value) {
            if (($frame[$key] ?? null) !== $value) {
                $this->invalidCursor();
            }
        }
        if (! in_array($frame['next_lane'] ?? null, ['head', 'backfill'], true)
            || ! array_key_exists('backfill_before', $frame)
            || ! is_bool($frame['cycle_incomplete'] ?? null) || ! is_bool($frame['unresolved'] ?? null)
            || ($frame['cycle_incomplete'] && ! $frame['unresolved'])) {
            $this->invalidCursor();
        }
        $before = $frame['backfill_before'];
        if ($before !== null) {
            if (! is_array($before) || count($before) !== 2
                || ! array_key_exists('created_at', $before) || ! array_key_exists('id', $before)
                || ! is_string($before['id']) || ! Str::isUuid($before['id'])
                || ($before['created_at'] !== null && (! is_string($before['created_at']) || ! $this->validTimestamp($before['created_at'])))) {
                $this->invalidCursor();
            }
        }

        return [
            'next_lane' => $frame['next_lane'], 'backfill_before' => $before,
            'cycle_incomplete' => $frame['cycle_incomplete'], 'unresolved' => $frame['unresolved'],
        ];
    }

    /**
     * Read index-ordered non-NULL timestamps before filling from the NULL bucket.
     * Tuple bounds avoid an OR seek or CASE sort; excluded snapshots may still require extra index visits.
     *
     * @param  Builder<BankTransaction>  $query
     * @param  array{created_at: ?string, id: string}|null  $before
     * @return Collection<int, BankTransaction>
     */
    private function readOrderedPage(Builder $query, int $limit, ?array $before): Collection
    {
        if ($before !== null && $before['created_at'] === null) {
            return (clone $query)->whereNull('created_at')->where('id', '<', $before['id'])
                ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
        }
        $dated = (clone $query)->whereNotNull('created_at');
        if ($before !== null) {
            $dated->whereRowValues(['created_at', 'id'], '<', [$before['created_at'], $before['id']]);
        }
        $rows = $dated->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
        $remaining = $limit - $rows->count();
        if ($remaining > 0) {
            $undated = (clone $query)->whereNull('created_at')
                ->orderByDesc('created_at')->orderByDesc('id')->limit($remaining)->get();
            $rows = $rows->concat($undated);
        }

        return $rows->values();
    }

    /**
     * Preserve the database's exact timestamp representation rather than inventing a monotone high-water mark.
     *
     * @return array{created_at: ?string, id: string}
     */
    private function position(BankTransaction $row): array
    {
        return ['created_at' => $row->getRawOriginal('created_at'), 'id' => (string) $row->getKey()];
    }

    /** Validate a complete stored timestamp before using a decrypted tuple in a database comparison. */
    private function validTimestamp(string $value): bool
    {
        if (! preg_match('/\A(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?\z/', $value, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (int) $parts[4] <= 23 && (int) $parts[5] <= 59 && (int) $parts[6] <= 59;
    }

    /**
     * Seal one authenticated frame with the same complete request binding for both candidate outcomes.
     *
     * @param  array<string, int|string>  $binding
     * @param  array{created_at: ?string, id: string}|null  $before
     */
    private function seal(array $binding, string $nextLane, ?array $before, bool $cycleIncomplete, bool $unresolved): string
    {
        return Crypt::encryptString(json_encode([
            ...$binding, 'next_lane' => $nextLane, 'backfill_before' => $before,
            'cycle_incomplete' => $cycleIncomplete, 'unresolved' => $unresolved,
        ], JSON_THROW_ON_ERROR));
    }

    /** Never return token contents, validation details or a reset instruction for unrelated transport/auth failures. */
    private function invalidCursor(): never
    {
        throw ValidationException::withMessages(['sync_cursor' => ['bank_sync_cursor_invalid']]);
    }
}
