<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run 
                            {--redis : Include Redis in backup}
                            {--no-files : Skip files backup}
                            {--no-env : Skip environment files backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a backup of the database, files, and optionally Redis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting backup...');

        $backupScript = base_path('backup.sh');

        if (!file_exists($backupScript)) {
            $this->error('Backup script not found: ' . $backupScript);
            return Command::FAILURE;
        }

        if (!is_executable($backupScript)) {
            $this->error('Backup script is not executable. Run: chmod +x ' . $backupScript);
            return Command::FAILURE;
        }

        // Build environment variables
        $env = [];
        
        if ($this->option('redis')) {
            $env['BACKUP_REDIS'] = 'true';
        }
        
        if ($this->option('no-files')) {
            $env['BACKUP_FILES'] = 'false';
        }
        
        if ($this->option('no-env')) {
            $env['BACKUP_ENV'] = 'false';
        }

        // Run backup script
        $process = new Process(
            [$backupScript],
            base_path(),
            $env,
            null,
            null
        );

        $process->setTimeout(3600); // 1 hour timeout

        try {
            $process->mustRun(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->error($buffer);
                } else {
                    $this->line($buffer);
                }
            });

            $this->info('Backup completed successfully!');
            
            Log::info('Backup completed successfully', [
                'redis' => $this->option('redis'),
                'files' => !$this->option('no-files'),
                'env' => !$this->option('no-env'),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            
            Log::error('Backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}

