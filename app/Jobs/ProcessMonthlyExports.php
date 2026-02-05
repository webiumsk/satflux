<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * End-of-month job: create automatic CSV exports for Pro (and above) users.
 * Idempotent: only creates one automatic export per store per month.
 */
class ProcessMonthlyExports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $forMonth = null // Y-m format; default previous month
    ) {}

    public function handle(SubscriptionService $subscriptionService): void
    {
        $month = $this->forMonth ?? now()->subMonth()->format('Y-m');
        $dateFrom = "{$month}-01";
        $lastDay = (new \DateTime($dateFrom))->modify('last day of this month')->format('Y-m-d');
        $dateTo = $lastDay;

        $planIds = SubscriptionPlan::where('is_active', true)
            ->get()
            ->filter(fn ($p) => in_array('automatic_csv_exports', $p->features ?? [], true))
            ->pluck('id')
            ->all();

        if (empty($planIds)) {
            return;
        }

        $userIds = \DB::table('subscriptions')
            ->whereIn('plan_id', $planIds)
            ->whereIn('status', ['active', 'grace'])
            ->where('expires_at', '>', now())
            ->pluck('user_id')
            ->unique()
            ->all();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user || !$subscriptionService->canUseAutomaticExports($user)) {
                continue;
            }

            foreach ($user->stores as $store) {
                $exists = Export::where('store_id', $store->id)
                    ->where('user_id', $user->id)
                    ->where('source', Export::SOURCE_AUTOMATIC)
                    ->where('filters->date_from', $dateFrom)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $export = Export::create([
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                    'source' => Export::SOURCE_AUTOMATIC,
                    'format' => 'standard',
                    'filters' => [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'status' => null,
                    ],
                ]);

                GenerateCsvExport::dispatch($export);
                Log::info('Scheduled automatic monthly export', [
                    'export_id' => $export->id,
                    'store_id' => $store->id,
                    'month' => $month,
                ]);
            }
        }
    }
}

