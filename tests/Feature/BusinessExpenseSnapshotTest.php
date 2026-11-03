<?php

namespace Tests\Feature;

use App\Http\Controllers\Invoicing\BusinessExpenseController;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Support\Invoicing\BusinessExpenseListSummary;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseSnapshotTest extends TestCase
{
    private const READER = 'expense_snapshot_reader';

    private const WRITER = 'expense_snapshot_writer';

    private ?Connection $reader = null;

    private ?Connection $writer = null;

    private ?string $sqlitePath = null;

    private ?string $originalDefault = null;

    private Company $company;

    private string $firstId;

    private string $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefault = DB::getDefaultConnection();
        $postgres = getenv('EXPENSE_SNAPSHOT_PG_TESTS') === '1';
        if (! app()->environment('testing')) {
            throw new \LogicException('Expense snapshot tests require the testing environment.');
        }
        if ($postgres) {
            $settings = config('database.connections.pgsql');
            if (! is_array($settings) || ($settings['database'] ?? null) !== 'satflux_expense_snapshot_test') {
                throw new \LogicException('Expense snapshot PostgreSQL tests require their dedicated disposable database.');
            }
        } else {
            $path = tempnam(sys_get_temp_dir(), 'satflux-expense-snapshot-');
            if ($path === false) {
                throw new \RuntimeException('Could not create the disposable SQLite database.');
            }
            $this->sqlitePath = $path;
            $settings = array_merge(config('database.connections.sqlite'), [
                'database' => $this->sqlitePath,
                'foreign_key_constraints' => true,
                'busy_timeout' => 500,
            ]);
        }

        config([
            'database.connections.'.self::READER => $settings,
            'database.connections.'.self::WRITER => $settings,
        ]);
        DB::setDefaultConnection(self::READER);
        $this->reader = DB::connection(self::READER);
        $this->writer = DB::connection(self::WRITER);
        $this->driver = $this->reader->getDriverName();
        $this->assertNotSame($this->reader->getPdo(), $this->writer->getPdo());
        if ($this->driver === 'sqlite') {
            $this->assertSame('wal', $this->reader->selectOne('PRAGMA journal_mode = WAL')->journal_mode);
        } else {
            $this->assertSame('read committed', $this->reader->selectOne('SHOW transaction_isolation')->transaction_isolation);
        }

        $schema = Schema::connection(self::READER);
        $schema->dropIfExists('business_expense_attachments');
        $schema->dropIfExists('business_expenses');
        $schema->create('business_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('status');
            $table->string('internal_number');
            foreach (['external_number', 'title', 'variable_symbol', 'internal_note', 'attachment_path'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('issue_date');
            $table->date('delivery_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('currency', 3);
            $table->timestamps();
        });
        $schema->create('business_expense_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_expense_id');
        });

        $this->company = new Company;
        $this->company->id = (string) Str::uuid();
        $this->firstId = $this->insertExpense('10.10', 'EUR', 'recorded', '2026-06-01');
        $this->insertExpense('0.20', 'USD', 'paid', '2026-05-01');
    }

    protected function tearDown(): void
    {
        try {
            foreach ([$this->reader, $this->writer] as $connection) {
                if ($connection !== null && $connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                }
            }
            if ($this->reader !== null) {
                Schema::connection(self::READER)->dropIfExists('business_expense_attachments');
                Schema::connection(self::READER)->dropIfExists('business_expenses');
            }
            DB::purge(self::READER);
            DB::purge(self::WRITER);
            if ($this->originalDefault !== null) {
                DB::setDefaultConnection($this->originalDefault);
            }
            if ($this->sqlitePath !== null) {
                foreach ([$this->sqlitePath, $this->sqlitePath.'-wal', $this->sqlitePath.'-shm'] as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array<string, array{string}> */
    public static function concurrentWrites(): array
    {
        return ['insert' => ['insert'], 'update' => ['update'], 'delete' => ['delete']];
    }

    #[Test]
    #[DataProvider('concurrentWrites')]
    public function controller_keeps_summary_count_and_page_in_one_snapshot_during_a_concurrent_commit(string $operation): void
    {
        $committed = false;
        $snapshotSettings = [];
        $this->reader->listen(function (QueryExecuted $event) use ($operation, &$committed, &$snapshotSettings) {
            if ($committed || $event->connection !== $this->reader
                || ! str_starts_with($event->sql, 'select "status", "total", "currency" from "business_expenses"')) {
                return;
            }
            $committed = true;
            if ($this->driver === 'pgsql') {
                $snapshotSettings = [
                    $this->reader->getPdo()->query('SHOW transaction_isolation')->fetchColumn(),
                    $this->reader->getPdo()->query('SHOW transaction_read_only')->fetchColumn(),
                ];
            }
            $this->writer->transaction(function () use ($operation) {
                if ($this->driver === 'pgsql') {
                    $this->writer->statement("SET LOCAL lock_timeout = '500ms'");
                }
                if ($operation === 'insert') {
                    $this->insertExpense('20.20', 'EUR', 'recorded', '2026-06-02', $this->writer);
                } elseif ($operation === 'update') {
                    $this->writer->table('business_expenses')->where('id', $this->firstId)
                        ->update(['total' => '99.99', 'currency' => 'CZK', 'status' => 'paid']);
                } else {
                    $this->writer->table('business_expenses')->where('id', $this->firstId)->delete();
                }
            });
            $this->assertSame(0, $this->writer->transactionLevel());
        });

        $response = $this->listing();
        $this->assertTrue($committed, 'The independent writer must commit between the summary and paginator reads.');
        $this->assertSame(['count' => 2, 'currencies' => [
            ['currency' => 'EUR', 'count' => 1, 'total' => '10.10', 'unpaid_total' => '10.10'],
            ['currency' => 'USD', 'count' => 1, 'total' => '0.20', 'unpaid_total' => '0.00'],
        ]], $response['summary']);
        $this->assertSame(2, $response['total']);
        $this->assertSame(2, $response['last_page']);
        $this->assertCount(1, $response['data']);
        $this->assertSame($this->firstId, $response['data'][0]['id']);
        $this->assertSame('10.10', $response['data'][0]['total']);
        $this->assertSame('EUR', $response['data'][0]['currency']);
        $this->assertSame('recorded', $response['data'][0]['status']);
        if ($this->driver === 'pgsql') {
            $this->assertSame(['repeatable read', 'on'], $snapshotSettings);
        }
        $this->assertCleanOwnedTransaction();

        $next = $this->listing();
        $this->assertSame($operation === 'insert' ? 3 : ($operation === 'delete' ? 1 : 2), $next['total']);
        $this->assertSame($next['total'], $next['summary']['count']);
        $this->assertSame($operation === 'insert' ? '20.20' : ($operation === 'update' ? '99.99' : '0.20'), $next['data'][0]['total']);
        $this->assertSame($operation === 'insert' ? 'EUR' : ($operation === 'update' ? 'CZK' : 'USD'), $next['data'][0]['currency']);
        $this->assertSame(match ($operation) {
            'insert' => ['count' => 3, 'currencies' => [
                ['currency' => 'EUR', 'count' => 2, 'total' => '30.30', 'unpaid_total' => '30.30'],
                ['currency' => 'USD', 'count' => 1, 'total' => '0.20', 'unpaid_total' => '0.00'],
            ]],
            'update' => ['count' => 2, 'currencies' => [
                ['currency' => 'CZK', 'count' => 1, 'total' => '99.99', 'unpaid_total' => '0.00'],
                ['currency' => 'USD', 'count' => 1, 'total' => '0.20', 'unpaid_total' => '0.00'],
            ]],
            'delete' => ['count' => 1, 'currencies' => [
                ['currency' => 'USD', 'count' => 1, 'total' => '0.20', 'unpaid_total' => '0.00'],
            ]],
        }, $next['summary']);
    }

    #[Test]
    public function owned_snapshot_rolls_back_after_an_actual_sql_error_without_changing_connection_defaults(): void
    {
        try {
            BusinessExpenseListSummary::withinSnapshot(BusinessExpense::query(), function () {
                $this->assertSame(1, $this->reader->transactionLevel());
                $this->reader->select('SELECT missing_snapshot_column FROM business_expenses');
            });
            $this->fail('The invalid SQL must fail.');
        } catch (QueryException) {
            $this->assertCleanOwnedTransaction();
            $this->assertSame(1, (int) $this->reader->selectOne('SELECT 1 AS value')->value);
        }
    }

    /** @return array<string, array{string}> */
    public static function stableOuterIsolations(): array
    {
        return ['repeatable read' => ['REPEATABLE READ'], 'serializable' => ['SERIALIZABLE']];
    }

    #[Test]
    #[DataProvider('stableOuterIsolations')]
    public function stable_outer_transaction_retains_its_uncommitted_rows_and_owns_its_commit(string $isolation): void
    {
        $this->beginStableOuter($isolation);
        $uncommittedId = $this->insertExpense('1.00', 'EUR', 'recorded', '2026-06-02');
        $summary = BusinessExpenseListSummary::withinSnapshot(BusinessExpense::query(), function () {
            $this->assertSame(2, $this->reader->transactionLevel());

            return BusinessExpenseListSummary::fromQuery(BusinessExpense::query());
        });
        $this->assertSame(3, $summary['count']);
        $this->assertSame('11.10', $summary['currencies'][0]['total']);
        $this->assertSame(1, $this->reader->transactionLevel());
        $this->assertFalse($this->writer->table('business_expenses')->where('id', $uncommittedId)->exists());
        if ($this->driver === 'pgsql') {
            $this->assertSame(strtolower($isolation), $this->reader->selectOne('SHOW transaction_isolation')->transaction_isolation);
            $this->assertSame('off', $this->reader->selectOne('SHOW transaction_read_only')->transaction_read_only);
        }
        $this->reader->rollBack();
        $this->assertFalse($this->reader->table('business_expenses')->where('id', $uncommittedId)->exists());
        $this->assertCleanOwnedTransaction();
    }

    #[Test]
    #[DataProvider('stableOuterIsolations')]
    public function failed_nested_snapshot_rolls_back_only_its_savepoint_and_leaves_the_outer_transaction_usable(string $isolation): void
    {
        $this->beginStableOuter($isolation);
        $uncommittedId = $this->insertExpense('1.00', 'EUR', 'recorded', '2026-06-02');
        try {
            BusinessExpenseListSummary::withinSnapshot(BusinessExpense::query(), function () {
                $this->reader->select('SELECT missing_snapshot_column FROM business_expenses');
            });
            $this->fail('The invalid SQL must fail.');
        } catch (QueryException) {
            $this->assertSame(1, $this->reader->transactionLevel());
            $this->assertSame(1, (int) $this->reader->selectOne('SELECT 1 AS value')->value);
            $this->assertTrue($this->reader->table('business_expenses')->where('id', $uncommittedId)->exists());
            $this->assertFalse($this->writer->table('business_expenses')->where('id', $uncommittedId)->exists());
        }
        $this->reader->rollBack();
        $this->assertCleanOwnedTransaction();
    }

    #[Test]
    public function nested_postgres_read_committed_is_rejected_before_expense_reads_or_a_savepoint(): void
    {
        if ($this->driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL default-isolation rejection is covered by the opt-in PostgreSQL run.');
        }
        $this->reader->beginTransaction();
        $this->reader->select('SELECT 1');
        $statements = [];
        $this->reader->listen(function (QueryExecuted $event) use (&$statements) {
            if ($event->connection === $this->reader) {
                $statements[] = $event->sql;
            }
        });
        try {
            BusinessExpenseListSummary::withinSnapshot(BusinessExpense::query(), fn () => $this->fail('An unstable outer snapshot must not read expenses.'));
            $this->fail('The unsupported outer transaction must be rejected.');
        } catch (\LogicException $error) {
            $this->assertSame('Expense listing requires a stable outer transaction snapshot.', $error->getMessage());
            $this->assertSame(['SHOW transaction_isolation'], $statements);
            $this->assertSame(1, $this->reader->transactionLevel());
            $this->assertSame(1, (int) $this->reader->selectOne('SELECT 1 AS value')->value);
        }
        $this->reader->rollBack();
        $this->assertCleanOwnedTransaction();
    }

    #[Test]
    public function sqlite_uncommitted_read_mode_is_rejected_and_not_silently_changed(): void
    {
        if ($this->driver !== 'sqlite') {
            $this->markTestSkipped('SQLite isolation-mode rejection is covered by the default SQLite run.');
        }
        $this->reader->statement('PRAGMA read_uncommitted = 1');
        try {
            BusinessExpenseListSummary::withinSnapshot(BusinessExpense::query(), fn () => $this->fail('Dirty-read mode must not read expenses.'));
            $this->fail('The unsupported SQLite mode must be rejected.');
        } catch (\LogicException $error) {
            $this->assertSame('Expense listing requires SQLite committed-read isolation.', $error->getMessage());
            $this->assertSame(0, $this->reader->transactionLevel());
            $this->assertSame(1, (int) $this->reader->selectOne('PRAGMA read_uncommitted')->read_uncommitted);
        } finally {
            $this->reader->statement('PRAGMA read_uncommitted = 0');
        }
    }

    #[Test]
    public function snapshot_uses_the_builders_named_connection_instead_of_the_default_connection(): void
    {
        $query = BusinessExpense::query();
        $this->assertNull($query->getModel()->getConnectionName());
        $this->assertSame($this->reader, $query->getQuery()->getConnection());
        DB::setDefaultConnection($this->originalDefault);
        $count = BusinessExpenseListSummary::withinSnapshot($query, function () use ($query) {
            $this->assertSame(1, $this->reader->transactionLevel());
            $this->assertSame(0, DB::connection($this->originalDefault)->transactionLevel());

            return $query->count();
        });
        $this->assertSame(2, $count);
        $this->assertCleanOwnedTransaction();
    }

    private function beginStableOuter(string $isolation): void
    {
        $this->reader->beginTransaction();
        if ($this->driver === 'pgsql') {
            $this->reader->statement('SET TRANSACTION ISOLATION LEVEL '.$isolation);
        }
    }

    private function assertCleanOwnedTransaction(): void
    {
        $this->assertSame(0, $this->reader->transactionLevel());
        $this->assertFalse($this->reader->getPdo()->inTransaction());
        if ($this->driver === 'pgsql') {
            $this->assertSame('read committed', $this->reader->selectOne('SHOW transaction_isolation')->transaction_isolation);
            $this->assertSame('read committed', $this->reader->selectOne('SHOW default_transaction_isolation')->default_transaction_isolation);
            $this->assertSame('off', $this->reader->selectOne('SHOW transaction_read_only')->transaction_read_only);
        } else {
            $this->assertSame(0, (int) $this->reader->selectOne('PRAGMA read_uncommitted')->read_uncommitted);
        }
    }

    /** @return array<string, mixed> */
    private function listing(): array
    {
        $request = Request::create('/snapshot-expenses', 'GET', ['per_page' => 1]);
        $response = app(BusinessExpenseController::class)->index($request, $this->company);

        return $response->getData(true);
    }

    private function insertExpense(string $total, string $currency, string $status, string $date, ?Connection $connection = null): string
    {
        $id = (string) Str::uuid();
        ($connection ?? $this->reader)->table('business_expenses')->insert([
            'id' => $id,
            'company_id' => $this->company->id,
            'internal_number' => 'SNAPSHOT-'.$id,
            'status' => $status,
            'issue_date' => $date,
            'total' => $total,
            'currency' => $currency,
            'created_at' => '2026-06-01 12:00:00',
            'updated_at' => '2026-06-01 12:00:00',
        ]);

        return $id;
    }
}
