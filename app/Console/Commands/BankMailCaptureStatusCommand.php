<?php

namespace App\Console\Commands;

use App\Services\Invoicing\BankMimeCaptureService;
use Illuminate\Console\Command;

class BankMailCaptureStatusCommand extends Command
{
    protected $signature = 'bank-mail:capture-status';

    protected $description = 'Report the private diagnostic slot state without message or owner identifiers';

    public function handle(BankMimeCaptureService $capture): int
    {
        $this->line(json_encode($capture->status(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
