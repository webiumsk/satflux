<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\ExpenseSnapshotTestDatabase;
use Tests\TestCase;

class BusinessExpenseQuotaConcurrencyTest extends TestCase
{
    private ?Connection $connection = null;

    private bool $ownsFixture = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('EXPENSE_QUOTA_PG_TESTS') !== '1') {
            $this->markTestSkipped('Requires the explicitly enabled disposable PostgreSQL quota target.');
        }
        if (! app()->environment('testing')) {
            throw new \LogicException('Quota concurrency tests require the testing environment.');
        }
        $settings = ExpenseSnapshotTestDatabase::configuration(config('database.connections.pgsql'));
        config(['database.connections.pgsql' => $settings, 'database.default' => 'pgsql', 'access.free_pro_enabled' => false]);
        DB::purge('pgsql');
        $this->connection = DB::connection('pgsql');
        ExpenseSnapshotTestDatabase::assertTarget($this->connection->getPdo(), $settings);
        $this->assertSame('read committed', $this->connection->selectOne('SHOW transaction_isolation')->transaction_isolation);
        $this->ownsFixture = true;
        $this->artisan('migrate:fresh', ['--database' => 'pgsql', '--drop-types' => true, '--force' => true])->assertSuccessful();
        foreach (['pro', 'enterprise'] as $code) {
            SubscriptionPlan::create([
                'code' => $code, 'name' => $code, 'display_name' => $code,
                'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
                'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->connection !== null) {
                if ($this->connection->transactionLevel() > 0) {
                    $this->connection->rollBack(0);
                }
                if ($this->ownsFixture) {
                    $this->connection->unprepared('DROP FUNCTION IF EXISTS satflux_quota_test_gate() CASCADE');
                }
                DB::purge('quota_other');
                DB::purge('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array<string, array{string, string, bool}> */
    public static function races(): array
    {
        return [
            'last free record' => ['free', 'record', false],
            'last purchased record' => ['purchased', 'record', false],
            'last free persistence callback' => ['free', 'persist', false],
            'last purchased persistence callback' => ['purchased', 'persist', false],
            'free unit precedes purchased credit' => ['mixed', 'persist', false],
            'unlimited remains unlimited' => ['unlimited', 'persist', false],
            'different users are independent' => ['free', 'persist', true],
        ];
    }

    #[Test]
    #[DataProvider('races')]
    public function simultaneous_parsed_requests_cannot_overdraw_user_quota(string $tier, string $mode, bool $differentUsers): void
    {
        $first = User::factory()->create(['role' => $tier === 'unlimited' ? 'enterprise' : 'pro']);
        $second = $differentUsers ? User::factory()->create(['role' => 'pro']) : $first;
        $limit = $tier === 'purchased' ? 0 : 1;
        if (in_array($tier, ['purchased', 'mixed'], true)) {
            ExpenseIsdocCreditBalance::create(['user_id' => $first->id, 'balance' => 1]);
        } elseif ($tier === 'unlimited') {
            ExpenseIsdocCreditBalance::create(['user_id' => $first->id, 'balance' => 5]);
        }
        $gate = random_int(1, 2147483647);
        $this->connection->unprepared(str_replace(':gate', (string) $gate, <<<'SQL'
CREATE OR REPLACE FUNCTION satflux_quota_test_gate() RETURNS trigger AS $body$
BEGIN
    IF NEW.action = 'business_expense.isdoc_extracted' THEN
        PERFORM pg_advisory_xact_lock(:gate);
    END IF;
    RETURN NEW;
END;
$body$ LANGUAGE plpgsql
SQL));
        $this->connection->unprepared('CREATE TRIGGER satflux_quota_test_gate BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION satflux_quota_test_gate()');
        $this->connection->select('SELECT pg_advisory_lock(CAST(? AS bigint))', [$gate]);
        $held = true;
        $names = ['quota_a_'.$gate, 'quota_b_'.$gate];
        $workers = [];
        $inputs = [];
        try {
            foreach ([$first->id, $second->id] as $index => $userId) {
                $input = new InputStream;
                $worker = new Process([PHP_BINARY, '-r', self::workerSource(), (string) $userId, $mode, (string) $limit, $names[$index]], base_path());
                $worker->setInput($input);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
                $inputs[] = $input;
            }
            $this->waitUntil(fn () => count(array_filter($workers, fn (Process $worker) => str_contains($worker->getOutput(), "READY\n"))) === 2);
            foreach ($inputs as $input) {
                $input->write("GO\n");
                $input->close();
            }
            // Both early checks passed. Old code blocks both stale decisions at
            // the audit insert; fixed code blocks one at its user's row lock.
            $blocked = [];
            $this->waitUntil(function () use ($names, &$blocked, $workers) {
                foreach ($workers as $worker) {
                    $worker->getOutput();
                }
                $blocked = $this->connection->select("SELECT application_name, wait_event FROM pg_stat_activity WHERE datname = current_database() AND application_name IN (?, ?) AND wait_event_type = 'Lock'", $names);

                return count($blocked) === 2;
            });
            $this->connection->select('SELECT pg_advisory_unlock(CAST(? AS bigint))', [$gate]);
            $held = false;
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), 'The synthetic quota worker must complete normally.');
                $lines = array_values(array_filter(explode("\n", trim($worker->getOutput()))));
                $result = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
                $this->assertArrayNotHasKey('unexpected', $result);
                $results[] = $result;
            }
            $expected = in_array($tier, ['unlimited', 'mixed'], true) || $differentUsers ? 2 : 1;
            $this->assertSame($expected, count(array_filter($results, fn ($result) => $result['ok'])));
            $this->assertSame($expected, AuditLog::where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->count());
            foreach ($results as $result) {
                if (! $result['ok']) {
                    $this->assertSame(422, $result['status']);
                }
            }
            if ($mode === 'persist') {
                $this->assertSame($expected, array_sum(array_column($results, 'callbacks')));
            }
            if (in_array($tier, ['purchased', 'mixed'], true)) {
                $this->assertSame(0, (int) ExpenseIsdocCreditBalance::where('user_id', $first->id)->value('balance'));
                $this->assertSame(1, AuditLog::where('metadata->billing', 'purchased')->count());
                $this->assertSame($tier === 'mixed' ? 1 : 0, AuditLog::where('metadata->billing', 'free')->count());
            } elseif ($tier === 'free') {
                $this->assertSame($expected, AuditLog::where('metadata->billing', 'free')->count());
            } else {
                $this->assertSame(0, AuditLog::whereNotNull('metadata->billing')->count());
                $this->assertSame(5, (int) ExpenseIsdocCreditBalance::where('user_id', $first->id)->value('balance'));
            }
            if ($differentUsers) {
                $this->assertSame(2, count(array_filter($blocked, fn ($row) => $row->wait_event === 'advisory')));
                $this->assertSame(1, AuditLog::where('user_id', $first->id)->count());
                $this->assertSame(1, AuditLog::where('user_id', $second->id)->count());
            }
            $this->assertSame(0, $this->connection->transactionLevel());
            $this->assertSame('read committed', $this->connection->selectOne('SHOW transaction_isolation')->transaction_isolation);
        } finally {
            if ($held) {
                $this->connection->select('SELECT pg_advisory_unlock(CAST(? AS bigint))', [$gate]);
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(0.1);
                }
            }
        }
    }

    #[Test]
    public function purchased_debit_and_audit_rollback_together_on_an_actual_sql_failure(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $user = User::factory()->create(['role' => 'pro']);
        ExpenseIsdocCreditBalance::create(['user_id' => $user->id, 'balance' => 1]);
        $this->connection->unprepared(<<<'SQL'
CREATE FUNCTION satflux_quota_test_gate() RETURNS trigger AS $body$
BEGIN
    RAISE EXCEPTION 'Synthetic quota audit failure';
END;
$body$ LANGUAGE plpgsql
SQL);
        $this->connection->unprepared('CREATE TRIGGER satflux_quota_test_gate BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION satflux_quota_test_gate()');
        try {
            app(BusinessExpenseIsdocQuotaService::class)->recordExtraction($user, null, null);
            $this->fail('The synthetic SQL error must propagate.');
        } catch (QueryException) {
            $this->assertSame(0, $this->connection->transactionLevel());
            $this->assertSame(1, (int) $this->connection->selectOne('SELECT 1 AS value')->value);
            $this->assertSame(1, (int) ExpenseIsdocCreditBalance::where('user_id', $user->id)->value('balance'));
            $this->assertSame(0, AuditLog::where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->count());
        }
    }

    #[Test]
    public function nested_read_committed_sql_failure_rolls_back_only_the_owned_savepoint(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $this->connection->beginTransaction();
        $this->connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            app(BusinessExpenseIsdocQuotaService::class)->withExtractionQuotaLock($user, function () {
                $this->connection->select('SELECT missing_quota_column FROM users');
            });
            $this->fail('The invalid SQL must propagate.');
        } catch (QueryException) {
            $this->assertSame(1, $this->connection->transactionLevel());
            $this->assertSame(1, (int) $this->connection->selectOne('SELECT 1 AS value')->value);
            $this->assertSame('read committed', $this->connection->selectOne('SHOW transaction_isolation')->transaction_isolation);
        }
        $this->connection->rollBack();
    }

    /** @return array<string, array{string}> */
    public static function staleIsolations(): array
    {
        return ['repeatable read' => ['REPEATABLE READ'], 'serializable' => ['SERIALIZABLE']];
    }

    #[Test]
    #[DataProvider('staleIsolations')]
    public function a_stale_outer_snapshot_is_rejected_before_locking_or_persisting(string $isolation): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $this->connection->beginTransaction();
        $this->connection->statement('SET TRANSACTION ISOLATION LEVEL '.$isolation);
        $this->connection->select('SELECT count(*) FROM audit_logs');
        $queries = [];
        $this->connection->listen(function (QueryExecuted $event) use (&$queries) {
            $queries[] = $event->sql;
        });
        try {
            app(BusinessExpenseIsdocQuotaService::class)->withExtractionQuotaLock($user, fn () => $this->fail('The stale snapshot must not enter persistence.'));
            $this->fail('An unsupported outer isolation must fail.');
        } catch (\LogicException $error) {
            $this->assertSame('Expense extraction quota requires a Read Committed outer transaction.', $error->getMessage());
            $this->assertSame(['SHOW transaction_isolation'], $queries);
            $this->assertSame(1, $this->connection->transactionLevel());
        }
        $this->connection->rollBack();
    }

    #[Test]
    public function a_different_user_connection_is_rejected_before_querying_or_persisting(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        config(['database.connections.quota_other' => config('database.connections.pgsql')]);
        $user->setConnection('quota_other');
        $queries = [];
        $this->connection->listen(function (QueryExecuted $event) use (&$queries) {
            $queries[] = $event->sql;
        });
        try {
            app(BusinessExpenseIsdocQuotaService::class)->withExtractionQuotaLock($user, fn () => $this->fail('Connection mismatch must not enter persistence.'));
            $this->fail('Connection mismatch must be rejected.');
        } catch (\LogicException $error) {
            $this->assertSame('Expense extraction quota accounting requires one database connection.', $error->getMessage());
            $this->assertSame([], $queries);
            $this->assertSame(0, $this->connection->transactionLevel());
            $this->assertInstanceOf(\Closure::class, DB::connection('quota_other')->getRawPdo());
        }
    }

    #[Test]
    public function a_serialization_exception_does_not_retry_the_side_effect_callback(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $calls = 0;
        try {
            app(BusinessExpenseIsdocQuotaService::class)->withExtractionQuotaLock($user, function () use (&$calls) {
                $calls++;
                throw new QueryException('pgsql', 'synthetic quota callback', [], new \PDOException('could not serialize access due to concurrent update', 40001));
            });
            $this->fail('The synthetic serialization error must propagate.');
        } catch (QueryException) {
            $this->assertSame(1, $calls);
            $this->assertSame(0, $this->connection->transactionLevel());
            $this->assertFalse($this->connection->getPdo()->inTransaction());
            $this->assertSame('read committed', $this->connection->selectOne('SHOW transaction_isolation')->transaction_isolation);
        }
    }

    private function waitUntil(\Closure $condition): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($condition()) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('The deterministic quota worker barrier was not reached.');
    }

    private static function workerSource(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$calls = 0;
try {
    if (getenv('EXPENSE_QUOTA_PG_TESTS') !== '1' || ! $app->environment('testing')) {
        throw new LogicException('Unsafe quota worker target.');
    }
    $settings = Tests\Support\ExpenseSnapshotTestDatabase::configuration(config('database.connections.pgsql'));
    $settings['application_name'] = $argv[4];
    config(['database.connections.pgsql' => $settings, 'database.default' => 'pgsql', 'access.free_pro_enabled' => false, 'invoicing.expense_isdoc_extract_free_limit' => (int) $argv[3]]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    $connection = Illuminate\Support\Facades\DB::connection('pgsql');
    Tests\Support\ExpenseSnapshotTestDatabase::assertTarget($connection->getPdo(), $settings);
    $user = App\Models\User::findOrFail((int) $argv[1]);
    $quota = $app->make(App\Services\Invoicing\BusinessExpenseIsdocQuotaService::class);
    $quota->assertCanExtract($user);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new LogicException('Missing quota worker release.');
    }
    if ($argv[2] === 'record') {
        $quota->recordExtraction($user, null, null);
    } else {
        $quota->withExtractionQuotaLock($user, function ($lockedUser) use ($quota, &$calls) {
            $calls++;
            $quota->recordExtraction($lockedUser, null, null);
        });
    }
    fwrite(STDOUT, json_encode(['ok' => true, 'callbacks' => $calls])."\n");
} catch (Illuminate\Validation\ValidationException $error) {
    fwrite(STDOUT, json_encode(['ok' => false, 'status' => 422, 'callbacks' => $calls])."\n");
} catch (Throwable $error) {
    fwrite(STDOUT, json_encode(['ok' => false, 'unexpected' => get_class($error), 'callbacks' => $calls])."\n");
}
PHP;
    }
}
