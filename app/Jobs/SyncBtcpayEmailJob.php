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
        $maskedEmail = $this->maskEmailAddress($this->newEmail);
        Log::error('SyncBtcpayEmailJob failed', [
            'user_id' => $this->userId,
            'new_email_masked' => $maskedEmail,
            'error' => $exception->getMessage(),
        ]);
    }

    private function maskEmailAddress(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $first = $local !== '' ? $local[0] : '*';

        return $first.'***@'.$domain;
    }
}

