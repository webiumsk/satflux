<?php

namespace App\Console\Commands;

use App\Services\ApiDocumentation\DocumentationExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExportApiDocumentation extends Command
{
    protected $signature = 'api-docs:export {--check : Fail if reviewed artifacts are stale}';

    protected $description = 'Generate bounded API contracts in an isolated testing environment.';

    public function handle(DocumentationExporter $exporter): int
    {
        if (! app()->environment('testing') || app()->configurationIsCached()
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('cache.default') !== 'array'
            || config('session.driver') !== 'array'
            || config('mail.default') !== 'array') {
            $this->error('API export requires testing, uncached configuration, SQLite :memory:, and array cache/session/mail.');

            return self::FAILURE;
        }
        Http::preventStrayRequests();
        // Schema inspection is confined to the fresh in-memory database.
        $this->callSilent('migrate', ['--force' => true]);
        foreach ($exporter->artifacts() as $file => $contents) {
            $path = base_path('docs/api/'.$file);
            if ($this->option('check')) {
                if (! is_file($path) || file_get_contents($path) !== $contents) {
                    $this->error('Stale API artifact: '.$file);

                    return self::FAILURE;
                }
            } else {
                file_put_contents($path, $contents);
            }
        }
        $this->info('API contracts and route inventory are current.');

        return self::SUCCESS;
    }
}
