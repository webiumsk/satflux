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

/** Bounded overlapping head reads alternate with a durable, stateless history frontier. */
final class BankEmailSyncFeed
{
    private const ORDER = 'created_at:null-last:desc,id:desc';

    /**
     * Fetch one bounded lane without counting or summarizing the company's history.
     * A candidate cursor advances only after the client has durably acknowledged its rows.
     *
     * @param  array<string, mixed>  $input
     * @return array{rows: Collection<int, BankTransaction>, sync: array{protocol: int, lane: string, next_cursor: string, retry_cursor: string, cycle_complete: bool}}
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
            'protocol' => 1,
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
        if ($lane === 'backfill' && $before !== null) {
            $this->seekBefore($query, $before);
        }
        $found = $query
            ->orderByRaw('CASE WHEN created_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($pageSize + 1)
            ->get();
        $hasMore = $found->count() > $pageSize;
        $rows = $found->take($pageSize)->values();
        $last = $rows->last();
        $lastPosition = $last === null ? null : $this->position($last);

        if ($lane === 'head') {
            // A successful head read can seed a new cycle, but cannot jump an
            // existing history frontier when newer rows arrive above it.
            $complete = $before === null && ! $hasMore;
            $nextBefore = $complete ? null : ($before ?? $lastPosition);
            $nextLane = $complete ? 'head' : 'backfill';
        } else {
            $complete = ! $hasMore;
            $nextBefore = $complete ? null : $lastPosition;
            $nextLane = 'head';
        }

        return [
            'rows' => $rows,
            'sync' => [
                'protocol' => 1,
                'lane' => $lane,
                'next_cursor' => $this->seal($binding, $nextLane, $nextBefore),
                // A known incomplete page yields its turn without declaring
                // any uncovered position processed. Unknown outcomes use neither.
                'retry_cursor' => $this->seal($binding, $lane === 'head' ? 'backfill' : 'head', $before),
                'cycle_complete' => $complete,
            ],
        ];
    }

    /**
     * Authenticate one complete cursor frame and reject every binding/schema mismatch uniformly.
     * Cursors carry no expiry or authorization; the route still enforces the current company owner.
     *
     * @param  array<string, int|string>  $binding
     * @return array{next_lane: string, backfill_before: array{created_at: ?string, id: string}|null}
     */
    private function readFrame(?string $cursor, array $binding): array
    {
        if ($cursor === null) {
            return ['next_lane' => 'head', 'backfill_before' => null];
        }
        try {
            $frame = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->invalidCursor();
        }
        $expectedKeys = [...array_keys($binding), 'next_lane', 'backfill_before'];
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
            || ! array_key_exists('backfill_before', $frame)) {
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

        return ['next_lane' => $frame['next_lane'], 'backfill_before' => $before];
    }

    /**
     * Keep NULL timestamps last and include every lower UUID within a timestamp tie.
     *
     * @param  Builder<BankTransaction>  $query
     * @param  array{created_at: ?string, id: string}  $before
     */
    private function seekBefore(Builder $query, array $before): void
    {
        if ($before['created_at'] === null) {
            $query->whereNull('created_at')->where('id', '<', $before['id']);

            return;
        }
        $query->where(function (Builder $older) use ($before): void {
            $older->whereNull('created_at')
                ->orWhere('created_at', '<', $before['created_at'])
                ->orWhere(function (Builder $tied) use ($before): void {
                    $tied->where('created_at', $before['created_at'])->where('id', '<', $before['id']);
                });
        });
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
    private function seal(array $binding, string $nextLane, ?array $before): string
    {
        return Crypt::encryptString(json_encode([
            ...$binding, 'next_lane' => $nextLane, 'backfill_before' => $before,
        ], JSON_THROW_ON_ERROR));
    }

    /** Never return token contents, validation details or a reset instruction for unrelated transport/auth failures. */
    private function invalidCursor(): never
    {
        throw ValidationException::withMessages(['sync_cursor' => ['bank_sync_cursor_invalid']]);
    }
}
