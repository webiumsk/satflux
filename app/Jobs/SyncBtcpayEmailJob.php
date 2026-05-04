<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BtcPay\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBtcpayEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $userId
    ) {}

    public function handle(UserService $userService): void
    {
        $user = User::find($this->userId);
        if (! $user || empty($user->btcpay_user_id) || empty($user->email)) {
            return;
        }

        $userService->updateUser((string) $user->btcpay_user_id, [
            // Always sync the latest persisted email to avoid stale queued payload updates.
            'email' => (string) $user->email,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $context = [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ];

        $user = User::find($this->userId);
        if ($user && filled($user->email)) {
            $email = (string) $user->email;
            $parts = explode('@', $email, 2);
            $context['sync_email_masked'] = count($parts) === 2
                ? (($parts[0] !== '' ? $parts[0][0] : '*').'***@'.$parts[1])
                : '***';
        }

        Log::error('SyncBtcpayEmailJob failed', $context);
    }
}
