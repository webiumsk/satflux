<?php

namespace App\Console\Commands;

use App\Services\Invoicing\RecurringDocumentGeneratorService;
use Illuminate\Console\Command;

class ProcessRecurringInvoicesCommand extends Command
{
    protected $signature = 'invoicing:process-recurring';

    protected $description = 'Issue business documents from due recurring profiles';

    public function handle(RecurringDocumentGeneratorService $generator): int
    {
        $count = $generator->generateDueProfiles();

        $this->info("Generated {$count} document(s) from recurring profiles.");

        return self::SUCCESS;
    }
}
