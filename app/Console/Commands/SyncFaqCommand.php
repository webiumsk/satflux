<?php

namespace App\Console\Commands;

use App\Services\Faq\FaqCatalog;
use App\Services\Faq\FaqSynchronizer;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class SyncFaqCommand extends Command
{
    protected $signature = 'faq:sync {--check : Validate reviewed files without database access} {--apply : Apply a conflict-free batch as drafts} {--user= : Existing support/admin user ID for attribution}';

    protected $description = 'Validate, preview or import reviewed multilingual FAQ content without overwriting manual edits';

    public function handle(FaqCatalog $catalog, FaqSynchronizer $synchronizer): int
    {
        try {
            if ($this->option('check') && ($this->option('apply') || $this->option('user') !== null)) {
                throw new RuntimeException('--check cannot be combined with database import options.');
            }
            if (! $this->option('apply') && $this->option('user') !== null) {
                throw new RuntimeException('--user requires --apply.');
            }
            $content = $catalog->load();
            if ($this->option('check')) {
                $this->info('FAQ catalog, five-language translations, documentation links and source fingerprints are valid.');

                return self::SUCCESS;
            }
            if ($this->option('apply')) {
                $actor = $this->option('user');
                if (! is_string($actor) || ! preg_match('/^[1-9][0-9]*$/D', $actor) || filter_var($actor, FILTER_VALIDATE_INT) === false) {
                    throw new RuntimeException('--apply requires --user=<existing support/admin numeric ID>.');
                }
                $plan = $synchronizer->apply($content, (int) $actor);
            } else {
                $plan = $synchronizer->preview($content);
            }
            $this->table(['Type', 'Slug', 'Action'], array_map(fn (array $row) => array_values($row), $plan));
            if (in_array('conflict', array_column($plan, 'action'), true)) {
                $this->error('Manual changes or unmanaged identifiers conflict with the catalog. Nothing was changed.');

                return self::FAILURE;
            }
            $this->info($this->option('apply') ? 'FAQ synchronization complete. New items remain unpublished drafts.' : 'Preview only. No database records were changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Database exceptions can contain merchant content; never print their queries or bindings.
            $this->error(get_class($exception) === RuntimeException::class ? $exception->getMessage() : 'FAQ synchronization failed. Check the catalog, migrations and database availability without exposing database contents.');

            return self::FAILURE;
        }
    }
}
