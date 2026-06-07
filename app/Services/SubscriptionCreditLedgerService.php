<?php

namespace App\Services;

use App\Models\SubscriptionCreditLedgerEntry;
use App\Models\User;
class SubscriptionCreditLedgerService
{
    public function record(
        User $user,
        int $amount,
        ?int $balanceAfter,
        string $currency,
        string $description,
        ?string $sourceKey = null,
        ?\DateTimeInterface $occurredAt = null,
    ): SubscriptionCreditLedgerEntry {
        if ($sourceKey) {
            $existing = SubscriptionCreditLedgerEntry::query()
                ->where('source_key', $sourceKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return SubscriptionCreditLedgerEntry::create([
            'user_id' => $user->id,
            'currency' => strtoupper($currency),
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'source_key' => $sourceKey,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @return array<int, array{date: string, description: string, amount: int, balance: int|null}>
     */
    public function listForUser(User $user, int $limit = 50): array
    {
        return SubscriptionCreditLedgerEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (SubscriptionCreditLedgerEntry $entry) => [
                'date' => $entry->occurred_at?->toIso8601String(),
                'description' => $entry->description,
                'amount' => $entry->amount,
                'balance' => $entry->balance_after,
            ])
            ->values()
            ->all();
    }
}
