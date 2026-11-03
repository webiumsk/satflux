<?php

namespace Tests\Support;

use Illuminate\Support\ConfigurationUrlParser;
use PDO;

final class ExpenseSnapshotTestDatabase
{
    public const POSTGRES_DATABASE = 'satflux_expense_snapshot_test';

    /**
     * Build a single-endpoint fixture configuration before resolving connections.
     * Inherited URLs, read/write routing, prefixes and PDO options are excluded.
     *
     * @param  array<string, mixed>  $inherited
     * @return array<string, mixed>
     */
    public static function configuration(array $inherited, ?string $sqlitePath = null): array
    {
        if ($sqlitePath === null) {
            if (($inherited['driver'] ?? null) !== 'pgsql'
                || ($inherited['database'] ?? null) !== self::POSTGRES_DATABASE) {
                throw new \LogicException('Expense snapshot PostgreSQL tests require their dedicated disposable database.');
            }
            $settings = array_merge(array_intersect_key($inherited, array_flip([
                'host', 'port', 'username', 'password', 'charset', 'sslmode',
            ])), [
                'driver' => 'pgsql',
                'database' => self::POSTGRES_DATABASE,
                'prefix' => '',
                'search_path' => 'public',
            ]);
        } else {
            if ($sqlitePath === '') {
                throw new \LogicException('Expense snapshot SQLite tests require their disposable file.');
            }
            $settings = [
                'driver' => 'sqlite',
                'database' => $sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => 500,
            ];
        }

        $resolved = (new ConfigurationUrlParser)->parseConfiguration($settings);
        if (($resolved['driver'] ?? null) !== ($sqlitePath === null ? 'pgsql' : 'sqlite')
            || ($resolved['database'] ?? null) !== ($sqlitePath ?? self::POSTGRES_DATABASE)) {
            throw new \LogicException('Expense snapshot fixture configuration did not resolve to its intended target.');
        }

        return $resolved;
    }

    /** @param array<string, mixed> $settings */
    public static function assertTarget(PDO $pdo, array $settings): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== ($settings['driver'] ?? null)) {
            throw new \LogicException('Expense snapshot fixture connection has an unexpected driver.');
        }
        if ($driver === 'pgsql') {
            $database = $pdo->query('SELECT current_database()')->fetchColumn();
            if ($database === self::POSTGRES_DATABASE && $database === ($settings['database'] ?? null)) {
                return;
            }
        } elseif ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $database) {
                if ($database['name'] === 'main' && $database['file'] === ($settings['database'] ?? null)) {
                    return;
                }
            }
        }

        throw new \LogicException('Expense snapshot fixture connection is not its verified disposable target.');
    }
}
