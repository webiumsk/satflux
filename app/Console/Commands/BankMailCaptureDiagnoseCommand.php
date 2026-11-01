<?php

namespace App\Console\Commands;

use App\Services\Invoicing\BankMimeCaptureService;
use Illuminate\Console\Command;

class BankMailCaptureDiagnoseCommand extends Command
{
    protected $signature = 'bank-mail:capture-diagnose';

    protected $description = 'Recheck one encrypted diagnostic message and print only bounded verification facts';

    public function handle(BankMimeCaptureService $capture): int
    {
        $result = $capture->diagnose();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['status'] === 'diagnosed' ? self::SUCCESS : self::FAILURE;
    }
}
