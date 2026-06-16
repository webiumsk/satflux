<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Invoicing\ServerToEvoluMigrationExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportInvoicingForEvoluCommand extends Command
{
    protected $signature = 'invoicing:export-for-evolu
                            {user : User email or UUID}
                            {--output= : Write JSON to this path (default: storage/app/private/invoicing-export-{userId}.json)}';

    protected $description = 'Export server PostgreSQL invoicing data as Evolu snapshot JSON for browser import';

    public function handle(ServerToEvoluMigrationExportService $exportService): int
    {
        $identifier = trim((string) $this->argument('user'));
        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $user) {
            $this->error("User not found: {$identifier}");

            return Command::FAILURE;
        }

        $result = $exportService->exportForUser($user);
        $companies = $result['counts']['company'] ?? 0;

        if ($companies === 0) {
            $this->warn("User {$user->email} has no server invoicing companies.");

            return Command::SUCCESS;
        }

        $output = $this->option('output')
            ?: storage_path('app/private/invoicing-export-'.$user->id.'.json');

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'user_id' => $user->id,
            'user_email' => $user->email,
            'warnings' => $result['warnings'],
            'counts' => $result['counts'],
            'data' => $result['snapshot'],
        ];

        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Exported {$companies} companies to {$output}");
        if ($result['warnings'] !== []) {
            $this->warn('Warnings: '.count($result['warnings']));
            foreach ($result['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }

        $this->newLine();
        $this->comment('Next: sign in as this user with VITE_INVOICING_LOCAL_FIRST=true, open Invoicing, and run "Import from server".');

        return Command::SUCCESS;
    }
}
