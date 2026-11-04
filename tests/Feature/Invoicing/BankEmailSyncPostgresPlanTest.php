<?php

namespace Tests\Feature\Invoicing;

use App\Models\BankTransaction;
use App\Models\Company;
use App\Services\Invoicing\BankEmailSyncFeed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

class BankEmailSyncPostgresPlanTest extends TestCase
{
    private const COMPANY = '11111111-1111-4111-8111-111111111111';

    private const OTHER_COMPANY = '22222222-2222-4222-8222-222222222222';

    private const INDEX = 'bank_transactions_sync_order_idx';

    private ?string $schema = null;

    /** @var array<string, mixed>|null */
    private ?array $originalConnection = null;

    /** Guard every write, then confine real PostgreSQL DDL and fixtures to a fresh private schema. */
    protected function setUp(): void
    {
        parent::setUp();
        $optedIn = env('BANK_SYNC_POSTGRES_TEST') === '1';
        if (config('database.default') !== 'pgsql' && ! $optedIn) {
            $this->markTestSkipped('The dedicated PostgreSQL CI job opts into these isolated plan and migration checks.');
        }
        $this->assertTrue($optedIn, 'PostgreSQL test DDL requires explicit BANK_SYNC_POSTGRES_TEST=1.');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('pgsql', config('database.default'));
        $connection = config('database.connections.pgsql');
        $this->assertIsArray($connection);
        $this->assertSame('satflux_bank_sync_testing', $connection['database']);
        $this->assertContains($connection['host'], ['127.0.0.1', 'localhost']);
        $this->assertSame('5432', (string) $connection['port']);
        foreach (['url', 'read', 'write'] as $key) {
            $this->assertEmpty($connection[$key] ?? null);
        }
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->assertSame('satflux_bank_sync_testing', DB::scalar('SELECT current_database()'));
        $this->assertTrue((int) DB::scalar('SHOW server_version_num') >= 160000);
        $this->assertTrue((int) DB::scalar('SHOW server_version_num') < 170000);
        $this->originalConnection = $connection;
        // The identifier comes only from this process's random bytes, never configuration or input.
        $schema = 'bank_sync_plan_'.bin2hex(random_bytes(8));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        $this->schema = $schema;
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->assertSame($schema, DB::scalar('SELECT current_schema()'));
            DB::statement('CREATE TABLE bank_transactions (
                id uuid PRIMARY KEY, company_id uuid NOT NULL, source varchar(32) NOT NULL,
                created_at timestamp NULL, updated_at timestamp NULL,
                booked_at timestamp NOT NULL, amount numeric(14,2) NOT NULL,
                currency varchar(3) NOT NULL, direction varchar(16) NOT NULL,
                match_status varchar(16) NOT NULL, dedupe_hash varchar(64) NOT NULL,
                counterparty_name text NULL, reference text NULL, business_expense_id uuid NULL
            )');
            DB::statement('CREATE TABLE bank_transaction_matches (id uuid PRIMARY KEY, bank_transaction_id uuid NOT NULL)');
            $this->app->make('migration.repository')->createRepository();
        } catch (Throwable $error) {
            $this->removeSchema();
            throw $error;
        }
    }

    /** Remove only the generated test schema, restoring the connection even after a failed assertion. */
    protected function tearDown(): void
    {
        try {
            $this->removeSchema();
        } finally {
            parent::tearDown();
        }
    }

    /** Never drop a configured schema or a database; the generated schema is the only cleanup target. */
    private function removeSchema(): void
    {
        try {
            if ($this->schema !== null) {
                if (! preg_match('/\Abank_sync_plan_[a-f0-9]{16}\z/', $this->schema)) {
                    throw new \RuntimeException('Refused cleanup outside the generated test schema.');
                }
                DB::statement('DROP SCHEMA "'.$this->schema.'" CASCADE');
                $this->schema = null;
            }
        } finally {
            if ($this->originalConnection !== null) {
                config(['database.connections.pgsql' => $this->originalConnection]);
                DB::purge('pgsql');
                $this->originalConnection = null;
            }
        }
    }

    /** Use deterministic, valid UUIDs to expose timestamp ties independently from insertion order. */
    private function uuid(int $number): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $number);
    }

    /** Seed enough mixed-company/source data for the ordinary planner, with a long excluded prefix. */
    private function seedRows(int $count): void
    {
        $rows = [];
        $base = strtotime('2026-11-03 12:00:00 UTC');
        for ($number = 1; $number <= $count; $number++) {
            $rows[] = [
                'id' => $this->uuid($number),
                'company_id' => $number % 3 === 0 ? self::OTHER_COMPANY : self::COMPANY,
                'source' => $number % 5 === 0 ? 'wise' : 'email',
                'created_at' => $number > 45000 ? null : gmdate('Y-m-d H:i:s', $base + intdiv($number, 10)),
                'updated_at' => null, 'booked_at' => '2026-11-03 12:00:00', 'amount' => '19.00',
                'currency' => 'EUR', 'direction' => 'debit', 'match_status' => 'unmatched',
                'dedupe_hash' => hash('sha256', (string) $number),
                'counterparty_name' => null,
                'reference' => $number >= 43000 || $number % 4 !== 0 ? 'Stav na ucte' : 'Synthetic payment',
                'business_expense_id' => null,
            ];
            if (count($rows) === 500 || $number === $count) {
                DB::table('bank_transactions')->insert($rows);
                $rows = [];
            }
        }
        DB::statement('ANALYZE bank_transactions');
    }

    /** Invoke the real service without creating unrelated application users, plans or companies. */
    private function feed(?string $cursor = null): array
    {
        $company = new Company;
        $company->forceFill(['id' => self::COMPANY]);

        return $this->app->make(BankEmailSyncFeed::class)->page($company, 'synthetic-plan-user', [
            'sync' => 1, 'source' => 'email', 'sync_context' => str_repeat('a', 64),
            'per_page' => 100, 'sync_cursor' => $cursor,
        ]);
    }

    /** Change only a synthetic authenticated bookmark so every actual query partition is exercised. */
    private function cursorBefore(string $cursor, ?string $createdAt, int $id): string
    {
        $frame = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
        $frame['next_lane'] = 'backfill';
        $frame['backfill_before'] = ['created_at' => $createdAt, 'id' => $this->uuid($id)];

        return Crypt::encryptString(json_encode($frame, JSON_THROW_ON_ERROR));
    }

    /** Walk JSON plans rather than matching textual plans or forcing planner settings. */
    private function planNodes(array $node): array
    {
        $nodes = [$node];
        foreach ($node['Plans'] ?? [] as $child) {
            array_push($nodes, ...$this->planNodes($child));
        }

        return $nodes;
    }

    /** Read the real catalog in the generated schema, including validity after a concurrent build. */
    private function indexStatus(): ?object
    {
        return DB::selectOne('SELECT i.indisvalid, i.indisready, i.indisunique
            FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = ? AND c.relname = ?', [$this->schema, self::INDEX]);
    }

    /** Require a usable non-unique index, not a silently retained invalid concurrent-build artifact. */
    private function assertValidIndex(): void
    {
        $status = $this->indexStatus();
        $this->assertNotNull($status);
        $this->assertContains($status->indisvalid, [true, 1, '1', 't']);
        $this->assertContains($status->indisready, [true, 1, '1', 't']);
        $this->assertNotContains($status->indisunique, [true, 1, '1', 't']);
    }

    /** The framework must honor withinTransaction=false while all four actual query shapes retain their index plans. */
    #[Test]
    public function actual_feed_partitions_use_the_sync_index_without_sorting_or_excluded_row_scans(): void
    {
        $this->seedRows(60000);
        $migration = database_path('migrations/2026_11_04_080000_add_bank_email_sync_order_index.php');
        $this->app->make(Migrator::class)->run([$migration]);
        $this->assertValidIndex();
        DB::statement('ANALYZE bank_transactions');
        $queries = [];
        $record = true;
        DB::listen(function (QueryExecuted $query) use (&$queries, &$record): void {
            if ($record && str_starts_with(strtolower($query->sql), 'select ')
                && str_contains($query->sql, 'from "bank_transactions"')) {
                $queries[] = [$query->sql, $query->bindings];
            }
        });
        $head = $this->feed();
        $this->assertCount(0, $head['rows'], 'A long snapshot-only prefix must produce a bounded empty movement page.');
        $this->assertFalse($head['sync']['cycle_complete']);
        $this->feed($head['sync']['next_cursor']);
        $this->feed($this->cursorBefore($head['sync']['next_cursor'], null, 59900));
        $this->feed($this->cursorBefore($head['sync']['next_cursor'], '1900-01-01 00:00:00', 1));
        $record = false;
        $this->assertCount(5, $queries, 'Head, tuple seek, NULL seek and dated-empty/NULL-fill must all run.');
        foreach ($queries as [$sql, $bindings]) {
            $where = substr($sql, strpos(strtolower($sql), ' where '));
            $this->assertStringNotContainsString('lower(', strtolower($where));
            $this->assertStringNotContainsString('case ', strtolower($where));
            $result = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$sql, $bindings);
            $plan = json_decode($result[0]->{'QUERY PLAN'}, true, 32, JSON_THROW_ON_ERROR);
            $nodes = $this->planNodes($plan[0]['Plan']);
            $this->assertNotEmpty(array_filter($nodes, fn (array $node): bool => ($node['Index Name'] ?? null) === self::INDEX));
            foreach ($nodes as $node) {
                $this->assertStringNotContainsString('Sort', $node['Node Type']);
                if (($node['Index Name'] ?? null) === self::INDEX) {
                    $this->assertLessThanOrEqual(101, $node['Actual Rows']);
                    $this->assertSame(0, $node['Rows Removed by Filter'] ?? 0);
                }
            }
        }

        // Follow real candidate cursors from the empty head instead of bypassing the snapshot prefix.
        $expectedId = $this->uuid(42992);
        $page = $head;
        $movement = null;
        for ($pageNumber = 0; $pageNumber < 32 && $movement === null; $pageNumber++) {
            $page = $this->feed($page['sync']['next_cursor']);
            $movement = $page['rows']->firstWhere('id', $expectedId);
        }
        $this->assertNotNull($movement, 'A genuine movement must remain reachable through the snapshot-only prefix within 32 next-cursor reads.');
        $this->assertSame($expectedId, $movement->id);
        $this->assertSame('Synthetic payment', $movement->reference);
        $this->assertSame(self::COMPANY, $movement->company_id);
        $this->assertSame('email', $movement->source);
        $this->assertSame('19.00', $movement->amount);
        $this->assertSame('EUR', $movement->currency);
        $this->assertArrayNotHasKey(BankTransaction::BANK_SYNC_MOVEMENT_FLAG, $movement->getAttributes());
    }

    /** A failed concurrent build remains visible; explicit drop/rebuild and framework rollback preserve all fixture rows. */
    #[Test]
    public function concurrent_migration_rejects_invalid_leftovers_and_rolls_back_without_data_loss(): void
    {
        $this->seedRows(20);
        $path = database_path('migrations/2026_11_04_080000_add_bank_email_sync_order_index.php');
        $migration = require $path;
        $this->assertFalse($migration->withinTransaction);
        $this->assertSame(0, DB::connection()->transactionLevel());
        try {
            // A deliberate uniqueness failure produces a real INVALID index under the owned name.
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY "bank_transactions_sync_order_idx" ON "bank_transactions" ("source")');
            $this->fail('The duplicate synthetic sources must fail a concurrent unique build.');
        } catch (QueryException $error) {
            $this->assertSame('23505', (string) $error->getCode());
        }
        $status = $this->indexStatus();
        $this->assertNotNull($status);
        $this->assertNotContains($status->indisvalid, [true, 1, '1', 't']);
        $migrator = $this->app->make(Migrator::class);
        try {
            $migrator->run([$path]);
            $this->fail('An existing INVALID index must not be treated as a successful migration.');
        } catch (QueryException $error) {
            $this->assertSame('42P07', (string) $error->getCode());
        }
        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertSame(20, DB::table('bank_transactions')->count());
        $migration->down();
        $this->assertNull($this->indexStatus());
        $migrator->run([$path]);
        $this->assertValidIndex();
        $this->assertSame(1, DB::table('migrations')->count());
        $migrator->rollback([$path]);
        $this->assertNull($this->indexStatus());
        $this->assertSame(20, DB::table('bank_transactions')->count());
        $migrator->run([$path]);
        $this->assertValidIndex();
        $this->assertSame(20, DB::table('bank_transactions')->count());
    }
}
