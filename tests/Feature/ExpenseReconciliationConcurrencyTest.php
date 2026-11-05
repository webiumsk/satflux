<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\ExpenseSnapshotTestDatabase;
use Tests\TestCase;

class ExpenseReconciliationConcurrencyTest extends TestCase
{
    private ?Connection $connection = null;

    /** Verify the opted-in disposable PostgreSQL target before rebuilding contention fixtures. */
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('EXPENSE_RECONCILIATION_PG_TESTS') !== '1') {
            $this->markTestSkipped('Requires the explicitly enabled disposable PostgreSQL reconciliation target.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());
        $settings = ExpenseSnapshotTestDatabase::configuration(config('database.connections.pgsql'));
        config(['database.connections.pgsql' => $settings, 'database.default' => 'pgsql', 'invoicing.local_first' => false]);
        DB::purge('pgsql');
        $this->connection = DB::connection('pgsql');
        ExpenseSnapshotTestDatabase::assertTarget($this->connection->getPdo(), $settings);
        $this->assertSame('read committed', $this->connection->selectOne('SHOW transaction_isolation')->transaction_isolation);
        $this->artisan('migrate:fresh', ['--database' => 'pgsql', '--drop-types' => true, '--force' => true])->assertSuccessful();
    }

    /** Release test transactions and connections even after a contention assertion fails. */
    protected function tearDown(): void
    {
        try {
            if ($this->connection !== null) {
                if ($this->connection->transactionLevel() > 0) {
                    $this->connection->rollBack(0);
                }
                DB::purge('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array{Company, BusinessExpense, BankTransaction} */
    private function fixture(): array
    {
        $company = Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic Contention Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $expense = BusinessExpense::create([
            'company_id' => $company->id,
            'internal_number' => 'CONTENTION-1',
            'external_number' => 'INV-001234',
            'variable_symbol' => '001234',
            'status' => BusinessExpenseStatus::Recorded,
            'issue_date' => '2026-10-30',
            'total' => '123.45',
            'currency' => 'EUR',
        ]);
        $transaction = $this->movement($company);

        return [$company, $expense, $transaction];
    }

    /** Create an independent recorded debit within the synthetic contention company. */
    private function movement(Company $company): BankTransaction
    {
        return BankTransaction::create([
            'company_id' => $company->id,
            'booked_at' => '2026-11-03 14:35:00',
            'amount' => '123.45',
            'currency' => 'EUR',
            'direction' => BankTransactionDirection::Debit,
            'match_status' => BankTransactionMatchStatus::Unmatched,
            'variable_symbol' => '001234',
            'source' => 'csv',
            'dedupe_hash' => (string) Str::uuid(),
        ]);
    }

    /** Prove expense creation locks its movement first and safely wins against concurrent matching. */
    #[Test]
    public function creating_an_expense_and_matching_the_same_movement_follow_one_lock_order(): void
    {
        [$company, $expense, $transaction] = $this->fixture();
        [$creator, $release] = $this->worker('create', $company, $expense, $transaction, 'bank_transactions');
        $matcher = null;
        try {
            $this->waitUntil(fn () => str_contains($creator->getOutput(), "LOCK_HELD\n"));
            $this->assertStringContainsString("FIRST_LOCK:bank_transactions\n", $creator->getOutput());
            [$matcher] = $this->worker('match', $company, $expense, $transaction);
            $this->waitForLock($matcher, 'bank_transactions');
            $release->write("GO\n");
            $release->close();
            $created = $this->workerResult($creator);
            $matched = $this->workerResult($matcher);

            $this->assertTrue($created['ok']);
            $this->assertSame(['ok' => false, 'status' => 422], $matched);
            $this->assertNotSame($expense->id, $transaction->fresh()->business_expense_id);
            $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
            $this->assertSame(2, BusinessExpense::count());
            $this->assertSame(1, AuditLog::where('action', 'bank_transaction.expense_created')->count());
            $this->assertSame(0, AuditLog::where('action', 'bank_transaction.expense_matched')->count());
        } finally {
            $release->close();
            $this->stop($creator, $matcher);
        }
    }

    /** Serialize competing confirmations into one bank link and one bank-match audit. */
    #[Test]
    public function two_movements_competing_for_one_expense_produce_one_link_and_one_audit(): void
    {
        [$company, $expense, $first] = $this->fixture();
        $second = $this->movement($company);
        [$winner, $release] = $this->worker('match', $company, $expense, $first, 'business_expenses');
        $other = null;
        try {
            $this->waitUntil(fn () => str_contains($winner->getOutput(), "LOCK_HELD\n"));
            [$other] = $this->worker('match', $company, $expense, $second);
            $this->waitForLock($other, 'companies');
            $release->write("GO\n");
            $release->close();

            $this->assertTrue($this->workerResult($winner)['ok']);
            $this->assertSame(['ok' => false, 'status' => 422], $this->workerResult($other));
            $this->assertSame($expense->id, $first->fresh()->business_expense_id);
            $this->assertNull($second->fresh()->business_expense_id);
            $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
            $this->assertSame(1, BankTransaction::where('business_expense_id', $expense->id)->count());
            $this->assertSame(1, AuditLog::where('action', 'bank_transaction.expense_matched')->count());
        } finally {
            $release->close();
            $this->stop($winner, $other);
        }
    }

    /** Prove both edit-first and match-first contention preserve compatible financial evidence. */
    #[Test]
    #[TestWith(['edit'])]
    #[TestWith(['match'])]
    public function financial_edits_and_matching_serialize_without_leaving_a_stale_payment_link(string $firstMode): void
    {
        [$company, $expense, $transaction] = $this->fixture();
        [$first, $release] = $this->worker($firstMode, $company, $expense, $transaction, 'business_expenses');
        $second = null;
        try {
            $this->waitUntil(fn () => str_contains($first->getOutput(), "LOCK_HELD\n"));
            [$second] = $this->worker($firstMode === 'edit' ? 'match' : 'edit', $company, $expense, $transaction);
            $this->waitForLock($second, 'companies');
            $release->write("GO\n");
            $release->close();

            $this->assertTrue($this->workerResult($first)['ok']);
            $this->assertSame(['ok' => false, 'status' => 422], $this->workerResult($second));
            if ($firstMode === 'edit') {
                $this->assertSame('200.00', $expense->fresh()->total);
                $this->assertSame(BusinessExpenseStatus::Recorded, $expense->fresh()->status);
                $this->assertNull($transaction->fresh()->business_expense_id);
                $this->assertSame(BankTransactionMatchStatus::Unmatched, $transaction->fresh()->match_status);
                $this->assertSame(0, AuditLog::where('action', 'business_expense.marked_paid')->count());
            } else {
                $this->assertSame('123.45', $expense->fresh()->total);
                $this->assertSame(BusinessExpenseStatus::Paid, $expense->fresh()->status);
                $this->assertSame('2026-11-03', $expense->fresh()->paid_at->toDateString());
                $this->assertSame($expense->id, $transaction->fresh()->business_expense_id);
                $this->assertSame(BankTransactionMatchStatus::Matched, $transaction->fresh()->match_status);
                $this->assertSame(1, AuditLog::where('action', 'business_expense.marked_paid')->count());
            }
        } finally {
            $release->close();
            $this->stop($first, $second);
        }
    }

    /** @return array{Process, InputStream} */
    private function worker(string $mode, Company $company, BusinessExpense $expense, BankTransaction $transaction, string $gateTable = ''): array
    {
        $name = 'expense_reconcile_'.bin2hex(random_bytes(6));
        $input = new InputStream;
        $worker = new Process([
            PHP_BINARY, '-r', self::workerSource(), $mode, $company->id, $expense->id,
            $transaction->id, $name, $gateTable,
        ], base_path());
        $worker->setInput($input);
        $worker->setTimeout(20);
        $worker->start();

        return [$worker, $input];
    }

    /** Confirm real PostgreSQL contention before releasing the competing operation. */
    private function waitForLock(Process $worker, string $table): void
    {
        $name = $worker->getCommandLine();
        preg_match('/expense_reconcile_[a-f0-9]+/', $name, $parts);
        $this->assertArrayHasKey(0, $parts);
        $this->waitUntil(function () use ($worker, $parts, $table): bool {
            $worker->getOutput();
            $row = $this->connection->selectOne(
                "SELECT query FROM pg_stat_activity WHERE datname = current_database() AND application_name = ? AND wait_event_type = 'Lock'",
                [$parts[0]],
            );

            return $row !== null && str_contains($row->query, '"'.$table.'"') && str_contains($row->query, 'for update');
        });
    }

    /** @return array<string, mixed> */
    private function workerResult(Process $worker): array
    {
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), 'The isolated worker must terminate normally.');
        $lines = array_values(array_filter(explode("\n", trim($worker->getOutput()))));
        $result = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('unexpected', $result, 'No deadlock, timeout or unexpected failure is allowed.');

        return $result;
    }

    /** Wait briefly for a deterministic worker barrier and fail if it is never reached. */
    private function waitUntil(\Closure $condition): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($condition()) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('The deterministic reconciliation worker barrier was not reached.');
    }

    /** Terminate remaining synthetic workers after a completed or failed contention scenario. */
    private function stop(?Process ...$workers): void
    {
        foreach ($workers as $worker) {
            if ($worker?->isRunning()) {
                $worker->stop(0.1);
            }
        }
    }

    /** Build guarded subprocess code that exercises actual services on the disposable PostgreSQL target. */
    private static function workerSource(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (getenv('EXPENSE_RECONCILIATION_PG_TESTS') !== '1' || ! $app->environment('testing') || $app->configurationIsCached()) {
        throw new LogicException('Unsafe reconciliation worker target.');
    }
    $settings = Tests\Support\ExpenseSnapshotTestDatabase::configuration(config('database.connections.pgsql'));
    $settings['application_name'] = $argv[5];
    config(['database.connections.pgsql' => $settings, 'database.default' => 'pgsql', 'invoicing.local_first' => false]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    $connection = Illuminate\Support\Facades\DB::connection('pgsql');
    Tests\Support\ExpenseSnapshotTestDatabase::assertTarget($connection->getPdo(), $settings);
    $connection->statement("SET lock_timeout = '8s'");
    $company = App\Models\Company::findOrFail($argv[2]);
    $movement = App\Models\BankTransaction::findOrFail($argv[4]);
    $gateTable = $argv[6];
    $mode = $argv[1];
    $held = false;
    $firstLock = null;
    $connection->listen(function (Illuminate\Database\Events\QueryExecuted $query) use ($gateTable, $mode, &$held, &$firstLock) {
        if ($mode === 'create' && $firstLock === null && str_contains($query->sql, 'for update')) {
            preg_match('/\bfrom\s+"([^"]+)"/i', $query->sql, $parts);
            $firstLock = $parts[1] ?? 'unknown';
            fwrite(STDOUT, 'FIRST_LOCK:'.$firstLock."\n");
            fflush(STDOUT);
            if ($firstLock !== 'bank_transactions') {
                throw new LogicException('Expense creation must lock its movement first.');
            }
        }
        if (! $held && $gateTable !== '' && str_starts_with($query->sql, 'select')
            && str_contains($query->sql, '"'.$gateTable.'"') && str_contains($query->sql, 'for update')) {
            $held = true;
            fwrite(STDOUT, "LOCK_HELD\n");
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'GO') {
                throw new LogicException('Missing reconciliation worker release.');
            }
        }
    });
    if ($argv[1] === 'create') {
        $app->make(App\Services\Invoicing\BankTransactionExpenseService::class)->createFromTransaction($company, $movement, []);
    } elseif ($argv[1] === 'edit') {
        $expense = App\Models\BusinessExpense::findOrFail($argv[3]);
        $app->make(App\Services\Invoicing\BusinessExpenseService::class)->update($expense, ['total' => '200.00']);
    } else {
        $app->make(App\Services\Invoicing\BusinessExpenseReconciliationService::class)->match($company, $movement, $argv[3], $company->user_id);
    }
    fwrite(STDOUT, json_encode(['ok' => true])."\n");
} catch (Illuminate\Validation\ValidationException $error) {
    fwrite(STDOUT, json_encode(['ok' => false, 'status' => 422])."\n");
} catch (Throwable $error) {
    fwrite(STDOUT, json_encode(['ok' => false, 'unexpected' => get_class($error)])."\n");
}
PHP;
    }
}
