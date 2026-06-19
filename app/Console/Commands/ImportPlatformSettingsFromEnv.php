<?php

namespace App\Console\Commands;

use App\Services\PlatformSettingsRepository;
use Illuminate\Console\Command;

class ImportPlatformSettingsFromEnv extends Command
{
    protected $signature = 'platform-settings:import-env
                            {--file=.env.standalone : Path to env file relative to project root}
                            {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import platform runtime settings from an env file into platform_settings table';

    public function handle(PlatformSettingsRepository $repository): int
    {
        $relative = (string) $this->option('file');
        $path = str_starts_with($relative, DIRECTORY_SEPARATOR)
            ? $relative
            : base_path($relative);

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $pairs = $this->parseEnvFile($path);

        if ($this->option('dry-run')) {
            $count = 0;
            foreach ($pairs as $envKey => $value) {
                $configKey = \App\Support\PlatformSettingsSchema::configKeyFromEnv($envKey);
                if ($configKey !== null && $value !== '') {
                    $this->line("{$envKey} → {$configKey}");
                    $count++;
                }
            }
            $this->info("Would import {$count} setting(s).");

            return self::SUCCESS;
        }

        $result = $repository->importFromEnvPairs($pairs);
        $this->info("Imported {$result['imported']} setting(s), skipped {$result['skipped']} empty value(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $pairs = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Strip inline comments for unquoted values
            if (! str_contains($line, '"') && ! str_contains($line, "'")) {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = trim(substr($value, 0, $hashPos));
                }
            }

            $pairs[$key] = $value;
        }

        return $pairs;
    }
}
