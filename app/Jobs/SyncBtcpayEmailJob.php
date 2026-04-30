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
        public int $userId,
        public string $newEmail
    ) {}

    public function handle(UserService $userService): void
    {
        $user = User::find($this->userId);
        if (! $user || empty($user->btcpay_user_id)) {
            return;
        }

        $userService->updateUser((string) $user->btcpay_user_id, [
            'email' => $this->newEmail,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBtcpayEmailJob failed', [
            'user_id' => $this->userId,
            'new_email' => $this->newEmail,
            'error' => $exception->getMessage(),
        ]);
    }
}

