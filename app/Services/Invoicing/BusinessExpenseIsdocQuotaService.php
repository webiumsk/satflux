<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\User;
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

        throw ValidationException::withMessages([
            'quota' => ['ISDOC extraction limit reached. Purchase a pack to continue.'],
        ]);
    }

    public function recordExtraction(User $user, ?string $expenseId, string $companyId): void
    {
        $snapshot = $this->snapshot($user);
        $metadata = ['company_id' => $companyId];

        if ($snapshot['unlimited']) {
            AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $user->id);

            return;
        }

        if ($snapshot['free_remaining'] > 0) {
            $metadata['billing'] = 'free';
            AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $user->id);

            return;
        }

        $this->packService->consumePurchasedCredit($user);
        $metadata['billing'] = 'purchased';
        AuditLog::log(self::EXTRACT_ACTION, 'business_expense', $expenseId, $metadata, $user->id);
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
