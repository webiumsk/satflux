<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackupListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:list 
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = base_path('backups');
        $metadataDir = $backupDir . '/metadata';

        if (!is_dir($metadataDir)) {
            $this->error('Backup metadata directory not found: ' . $metadataDir);
            return Command::FAILURE;
        }

        $backups = $this->loadBackups($metadataDir);

        if ($backups->isEmpty()) {
            $this->warn('No backups found.');
            return Command::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($backups->toJson(JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->info('Available backups:');
        $this->newLine();

        $tableData = [];
        foreach ($backups as $backup) {
            $tableData[] = [
                $backup['timestamp'],
                $backup['created_at'],
                implode(', ', $backup['components']),
                $backup['total_size'],
            ];
        }

        $this->table(
            ['Timestamp', 'Created At', 'Components', 'Total Size'],
            $tableData
        );

        return Command::SUCCESS;
    }

    /**
     * Load all backup metadata files.
     */
    protected function loadBackups(string $metadataDir): Collection
    {
        $backups = collect();

        $files = glob($metadataDir . '/satflux.io_backup_*.json');

        foreach ($files as $file) {
            try {
                $metadata = json_decode(file_get_contents($file), true);

                if (!$metadata) {
                    continue;
                }

                // Calculate total size
                $totalSize = 0;
                if (isset($metadata['database']['size_bytes'])) {
                    $totalSize += $metadata['database']['size_bytes'];
                }
                if (isset($metadata['files']['size_bytes'])) {
                    $totalSize += $metadata['files']['size_bytes'];
                }
                if (isset($metadata['redis']['size_bytes'])) {
                    $totalSize += $metadata['redis']['size_bytes'];
                }

                $backups->push([
                    'timestamp' => $metadata['timestamp'] ?? 'unknown',
                    'created_at' => $metadata['created_at'] ?? 'unknown',
                    'components' => $metadata['components'] ?? [],
                    'total_size' => $this->formatBytes($totalSize),
                    'total_size_bytes' => $totalSize,
                    'database' => $metadata['database'] ?? null,
                    'files' => $metadata['files'] ?? null,
                    'redis' => $metadata['redis'] ?? null,
                ]);
            } catch (\Exception $e) {
                $this->warn('Failed to read backup metadata: ' . basename($file));
                continue;
            }
        }

        // Sort by timestamp (newest first)
        return $backups->sortByDesc('timestamp');
    }

    /**
     * Format bytes to human-readable size.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}




