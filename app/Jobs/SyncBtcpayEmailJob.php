<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BtcPay\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Schema;
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
        if (! $user || empty($user->email)) {
            return;
        }

        if (Schema::hasColumn('users', 'allows_satflux_email_changes') && ! ($user->allows_satflux_email_changes ?? false)) {
            return;
        }

        if (! empty($user->btcpay_api_key)) {
            $userService->updateCurrentUserProfile($user->getBtcPayApiKeyOrFail(), [
                'email' => (string) $user->email,
            ]);

            return;
        }

        if (empty($user->btcpay_user_id)) {
            return;
        }

        $userService->updateUser((string) $user->btcpay_user_id, [
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
