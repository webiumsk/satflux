<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\User;
use App\Services\PurchaseAvailabilityPolicy;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessExpenseIsdocQuotaService
{
    public const EXTRACT_ACTION = 'business_expense.isdoc_extracted';

    public function __construct(
        protected BusinessExpenseIsdocPackService $packService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $unlimited = $user->planFeature('expense_isdoc_extract_unlimited');
        $freeLimit = (int) config('invoicing.expense_isdoc_extract_free_limit', 20);
        $freeUsed = $this->freeUsedCount($user);
        $freeRemaining = max(0, $freeLimit - $freeUsed);
        $purchasedCredits = $this->packService->purchasedBalance($user);

        $canExtract = $unlimited || $freeRemaining > 0 || $purchasedCredits > 0;

        return [
            'unlimited' => $unlimited,
            'free_used' => min($freeUsed, $freeLimit),
            'free_limit' => $freeLimit,
            'free_remaining' => $freeRemaining,
            'purchased_credits' => $purchasedCredits,
            'can_extract' => $canExtract,
            'packs' => $this->packService->availablePacks(),
            'purchases_available' => app(PurchaseAvailabilityPolicy::class)->snapshot()['isdoc_packs'],
            // Legacy keys for older UI
            'used' => min($freeUsed, $freeLimit),
            'limit' => $freeLimit,
            'remaining' => $freeRemaining,
        ];
    }

    public function assertCanExtract(User $user): void
    {
        if ($this->snapshot($user)['can_extract']) {
            return;
        }

        $message = app(PurchaseAvailabilityPolicy::class)->snapshot()['isdoc_packs']
            ? 'ISDOC extraction limit reached. Purchase a pack to continue.'
            : __('messages.isdoc_extract_limit_reached_no_purchase');

        throw ValidationException::withMessages(['quota' => [$message]]);
    }

    public function recordExtraction(User $user, ?string $expenseId, ?string $companyId): void
    {
        $this->withExtractionQuotaLock($user, function (User $lockedUser) use ($expenseId, $companyId) {
            $snapshot = $this->snapshot($lockedUser);
            $metadata = $companyId === null ? [] : ['company_id' => $companyId];

            if ($snapshot['unlimited']) {
                AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $lockedUser->id);

                return;
            }

            if ($snapshot['free_remaining'] > 0) {
                $metadata['billing'] = 'free';
                AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $lockedUser->id);

                return;
            }

            $this->packService->consumePurchasedCredit($lockedUser);
            $metadata['billing'] = 'purchased';
            AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $lockedUser->id);
        });
    }

    /**
     * Recheck quota after parsing, before persistence, while serializing this
     * user's usage and credit accounting. Side-effect callbacks run once.
     *
     * @template T
     *
     * @param  Closure(User): T  $callback
     * @return T
     */
    public function withExtractionQuotaLock(User $user, Closure $callback): mixed
    {
        $userQuery = $user->newQuery();
        $connection = $userQuery->getQuery()->getConnection();
        if (! $connection instanceof Connection
            || $connection !== DB::connection()
            || $connection !== AuditLog::query()->getQuery()->getConnection()
            || $connection !== ExpenseIsdocCreditBalance::query()->getQuery()->getConnection()) {
            throw new \LogicException('Expense extraction quota accounting requires one database connection.');
        }

        $driver = $connection->getDriverName();
        $ownsTransaction = $connection->transactionLevel() === 0;
        if ($driver === 'pgsql' && ! $ownsTransaction) {
            $isolation = $connection->selectOne('SHOW transaction_isolation', [], false);
            if ($isolation?->transaction_isolation !== 'read committed') {
                throw new \LogicException('Expense extraction quota requires a Read Committed outer transaction.');
            }
        } elseif ($driver !== 'pgsql' && $driver !== 'sqlite') {
            throw new \LogicException('Expense extraction quota is unsupported for this database driver.');
        }

        return $connection->transaction(function () use ($connection, $driver, $ownsTransaction, $userQuery, $user, $callback) {
            if ($driver === 'pgsql' && $ownsTransaction) {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            } elseif ($driver === 'sqlite') {
                $settings = $connection->selectOne('PRAGMA read_uncommitted', [], false);
                if ((int) ($settings->read_uncommitted ?? 1) !== 0) {
                    throw new \LogicException('Expense extraction quota requires SQLite committed-read isolation.');
                }
            }

            $lockedUser = $userQuery->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $this->assertCanExtract($lockedUser);

            return $callback($lockedUser);
        }, 1);
    }

    protected function freeUsedCount(User $user): int
    {
        $total = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('action', self::EXTRACT_ACTION)
            ->count();

        $purchased = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('action', self::EXTRACT_ACTION)
            ->where('metadata->billing', 'purchased')
            ->count();

        return max(0, $total - $purchased);
    }
}
