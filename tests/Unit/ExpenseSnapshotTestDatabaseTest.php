<?php

namespace Tests\Unit;

use Illuminate\Support\ConfigurationUrlParser;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExpenseSnapshotTestDatabase;

class ExpenseSnapshotTestDatabaseTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function poisonedUrls(): array
    {
        return [
            'PostgreSQL URL overrides its raw database' => ['pgsql', 'pgsql://url_user:synthetic@url-target.invalid:5544/url_database?database=url_query_database'],
            'SQLite URL overrides the temporary file' => ['sqlite', 'sqlite:////synthetic-never-open-snapshot.sqlite?database=url_query_database'],
            'URL also overrides the guarded driver' => ['pgsql', 'sqlite:////synthetic-never-open-snapshot.sqlite?database=url_query_database'],
        ];
    }

    #[Test]
    #[DataProvider('poisonedUrls')]
    public function fixture_configuration_discards_urls_and_routing_before_any_connection_is_created(string $driver, string $url): void
    {
        $expectedDatabase = $driver === 'pgsql'
            ? ExpenseSnapshotTestDatabase::POSTGRES_DATABASE
            : '/synthetic-unopened-temp/satflux-expense-snapshot.sqlite';
        $inherited = [
            'driver' => $driver,
            'database' => $expectedDatabase,
            'host' => 'approved-disposable-postgres.invalid',
            'port' => 5432,
            'username' => 'synthetic_snapshot_user',
            'password' => 'synthetic_snapshot_password',
            'url' => $url,
            'read' => ['url' => $url, 'database' => 'read_target', 'host' => 'read-target.invalid'],
            'write' => ['url' => $url, 'database' => 'write_target', 'host' => 'write-target.invalid'],
            'prefix' => 'inherited_schema.',
            'search_path' => 'inherited_schema',
            'options' => [PDO::ATTR_PERSISTENT => true],
        ];
        $parser = new ConfigurationUrlParser;

        // Negative control uses Laravel's real parser only. It opens no PDO,
        // touches no file and demonstrates why checking the raw name is unsafe.
        $unsafe = $parser->parseConfiguration($inherited);
        $this->assertSame('url_query_database', $unsafe['database']);
        $this->assertNotSame($expectedDatabase, $unsafe['database']);
        if ($driver === 'pgsql' && str_starts_with($url, 'sqlite:')) {
            $this->assertSame('sqlite', $unsafe['driver']);
        }

        $settings = ExpenseSnapshotTestDatabase::configuration($inherited, $driver === 'sqlite' ? $expectedDatabase : null);
        $resolved = $parser->parseConfiguration($settings);
        $this->assertSame($driver, $resolved['driver']);
        $this->assertSame($expectedDatabase, $resolved['database']);
        $this->assertSame('', $resolved['prefix']);
        foreach (['url', 'read', 'write', 'options'] as $excluded) {
            $this->assertArrayNotHasKey($excluded, $resolved);
        }
        if ($driver === 'pgsql') {
            $this->assertSame('approved-disposable-postgres.invalid', $resolved['host']);
            $this->assertSame(5432, $resolved['port']);
            $this->assertSame('public', $resolved['search_path']);
        } else {
            $this->assertSame([
                'driver' => 'sqlite',
                'database' => $expectedDatabase,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => 500,
            ], $resolved);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPostgresSettings(): array
    {
        return [
            'wrong driver' => ['sqlite', ExpenseSnapshotTestDatabase::POSTGRES_DATABASE],
            'wrong database' => ['pgsql', 'synthetic_other_database'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPostgresSettings')]
    public function postgres_configuration_rejects_an_unguarded_target_without_resolving_a_connection(string $driver, string $database): void
    {
        $this->expectException(\LogicException::class);
        ExpenseSnapshotTestDatabase::configuration(['driver' => $driver, 'database' => $database]);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function reportedTargets(): array
    {
        return [
            'dedicated PostgreSQL database' => ['pgsql', ExpenseSnapshotTestDatabase::POSTGRES_DATABASE, true],
            'different PostgreSQL database' => ['pgsql', 'synthetic_other_database', false],
            'owned SQLite file' => ['sqlite', '/synthetic-unopened-temp/owned.sqlite', true],
            'different SQLite file' => ['sqlite', '/synthetic-unopened-temp/other.sqlite', false],
        ];
    }

    #[Test]
    #[DataProvider('reportedTargets')]
    public function actual_connection_identity_must_match_before_fixture_schema_can_be_owned(string $driver, string $reported, bool $allowed): void
    {
        // Mocked metadata avoids opening any database, including poisoned URLs.
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $pdo->expects($this->once())->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn($driver);
        $settings = ['driver' => $driver, 'database' => $driver === 'pgsql'
            ? ExpenseSnapshotTestDatabase::POSTGRES_DATABASE
            : '/synthetic-unopened-temp/owned.sqlite'];
        if ($driver === 'pgsql') {
            $pdo->expects($this->once())->method('query')->with('SELECT current_database()')->willReturn($statement);
            $statement->expects($this->once())->method('fetchColumn')->willReturn($reported);
        } else {
            $pdo->expects($this->once())->method('query')->with('PRAGMA database_list')->willReturn($statement);
            $statement->expects($this->once())->method('fetchAll')->with(PDO::FETCH_ASSOC)
                ->willReturn([['seq' => 0, 'name' => 'main', 'file' => $reported]]);
        }
        if (! $allowed) {
            $this->expectException(\LogicException::class);
        }
        ExpenseSnapshotTestDatabase::assertTarget($pdo, $settings);
        $this->assertTrue($allowed);
    }
}
