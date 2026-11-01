<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Invoicing\BankMimeCaptureService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BankMailCaptureArmCommand extends Command
{
    protected $signature = 'bank-mail:capture-arm {store : Existing local Store UUID}
        {--minutes=120 : Absolute retention window, between 1 and 120 minutes}
        {--acknowledge-backup-retention : Accept existing encrypted Redis backup retention}';

    protected $description = 'Arm one private bank MIME diagnostic slot without changing payment decisions';

    public function handle(BankMimeCaptureService $capture): int
    {
        try {
            $status = 'disabled';
            if (config('bank_mail_capture.enabled', false)) {
                if (! $this->option('acknowledge-backup-retention')) {
                    $status = 'acknowledgement_required';
                } elseif (! Str::isUuid($this->argument('store'))) {
                    $status = 'invalid_store';
                } elseif (! is_string($this->option('minutes')) || ! preg_match('/^[0-9]{1,3}$/D', $this->option('minutes'))
                    || (int) $this->option('minutes') < 1 || (int) $this->option('minutes') > 120) {
                    $status = 'invalid_window';
                } else {
                    $store = Store::query()->find($this->argument('store'));
                    $status = $store === null ? 'invalid_store' : $capture->arm($store, (int) $this->option('minutes'));
                }
            }
        } catch (\Throwable) {
            $status = 'unavailable';
        }
        $this->line(json_encode(['version' => 1, 'status' => $status], JSON_THROW_ON_ERROR));

        return $status === 'armed' ? self::SUCCESS : self::FAILURE;
    }
}
