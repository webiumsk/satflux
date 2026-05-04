<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuestPurgeService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected GuestBtcPayDecommissioner $guestBtcPayDecommissioner,
    ) {}

    /**
     * @return array{considered: int, purged: int, skipped_active: int, skipped_error: int, dry_run_would_purge: int}
     */
    public function run(bool $dryRun, ?int $daysOverride = null): array
    {
        $idleDays = $daysOverride ?? (int) config('guest.idle_days', 90);
        $idleDays = max(1, $idleDays);
        $batchSize = max(1, (int) config('guest.batch_size', 50));

        $loginThreshold = Carbon::now()->subDays($idleDays);

        $stats = [
            'considered' => 0,
            'purged' => 0,
            'skipped_active' => 0,
            'skipped_error' => 0,
            'dry_run_would_purge' => 0,
        ];

        User::query()
            ->where('is_guest', true)
            ->with('stores')
            ->chunkById($batchSize, function ($users) use (
                &$stats,
                $dryRun,
                $idleDays,
                $loginThreshold
            ) {
                foreach ($users as $user) {
                    $stats['considered']++;

                    if (! $this->isLoginStale($user, $loginThreshold)) {
                        $stats['skipped_active']++;

                        continue;
                    }

                    try {
                        $hasInvoice = $this->hasInvoiceInWindow($user, $idleDays);
                    } catch (\Throwable $e) {
                        Log::warning('Guest purge: invoice check failed, skipping user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                        $stats['skipped_error']++;

                        continue;
                    }

                    if ($hasInvoice) {
                        $stats['skipped_active']++;

                        continue;
                    }

                    if ($dryRun) {
                        $stats['dry_run_would_purge']++;
                        Log::info('Guest purge dry-run: would purge inactive guest', [
                            'user_id' => $user->id,
                            'email_masked' => $this->maskGuestEmail((string) $user->email),
                            'store_ids' => $user->stores->pluck('id')->all(),
                            'idle_days' => $idleDays,
                        ]);

                        continue;
                    }

                    $this->purgeOneGuest($user);
                    $stats['purged']++;
                }
            });

        return $stats;
    }

    protected function isLoginStale(User $user, Carbon $loginThreshold): bool
    {
        if ($user->last_login_at === null) {
            return true;
        }

        return $user->last_login_at->lt($loginThreshold);
    }

    /**
     * True if any store has at least one invoice with createdTime in the window.
     */
    protected function hasInvoiceInWindow(User $user, int $idleDays): bool
    {
        $start = Carbon::now()->subDays($idleDays)->startOfDay()->timestamp;
        $end = Carbon::now()->timestamp;
        $filters = [
            'startDate' => $start,
            'endDate' => $end,
        ];

        $apiKey = $user->btcpay_api_key;

        foreach ($user->stores as $store) {
            if (! $store instanceof Store || ! $store->btcpay_store_id) {
                continue;
            }

            $response = $this->invoiceService->listInvoices(
                $store->btcpay_store_id,
                $filters,
                0,
                1,
                $apiKey,
            );

            $list = $response['data'] ?? $response;
            if (is_array($list) && count($list) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function purgeOneGuest(User $user): void
    {
        $userId = $user->id;
        $emailMasked = $this->maskGuestEmail((string) $user->email);
        $storeIds = $user->stores->pluck('id')->all();

        Log::info('Guest purge: purging inactive guest', [
            'user_id' => $userId,
            'email_masked' => $emailMasked,
            'store_ids' => $storeIds,
        ]);

        $this->guestBtcPayDecommissioner->decommissionAllForLocalGuestUser($user);

        DB::transaction(function () use ($userId) {
            $user = User::query()->where('id', $userId)->where('is_guest', true)->first();
            if ($user) {
                $user->delete();
            }
        });
    }

    protected function maskGuestEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 4) {
            return '***@'.$domain;
        }

        return substr($local, 0, 3).'***@'.$domain;
    }
}
