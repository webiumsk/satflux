<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes used by queries: btcpay_store_id (stores), processed_at (webhook_events),
     * role (users), expires_at (lnurl_auth_challenges, store_api_keys, exports).
     */
    public function up(): void
    {
        $this->addIndexIfNotExists('stores', 'btcpay_store_id', 'stores_btcpay_store_id_index');
        $this->addIndexIfNotExists('webhook_events', 'processed_at', 'webhook_events_processed_at_index');
        $this->addIndexIfNotExists('users', 'role', 'users_role_index');
        $this->addIndexIfNotExists('lnurl_auth_challenges', 'expires_at', 'lnurl_auth_challenges_expires_at_index');
        $this->addIndexIfNotExists('store_api_keys', 'expires_at', 'store_api_keys_expires_at_index');
        $this->addIndexIfNotExists('exports', 'expires_at', 'exports_expires_at_index');
    }

    private function addIndexIfNotExists(string $tableName, string $column, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }
        Schema::table($tableName, function (Blueprint $table) use ($column) {
            $table->index($column);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $schema = Schema::getConnection()->getSchemaBuilder();

        if ($driver === 'pgsql') {
            $result = Schema::getConnection()->selectOne(
                'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
                ['public', $tableName, $indexName]
            );
            return $result !== null;
        }
        if ($driver === 'mysql') {
            $db = Schema::getConnection()->getDatabaseName();
            $result = Schema::getConnection()->selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$db, $tableName, $indexName]
            );
            return $result !== null;
        }
        if ($driver === 'sqlite') {
            $result = Schema::getConnection()->selectOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$tableName, $indexName]
            );
            return $result !== null;
        }
        return false;
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['btcpay_store_id']);
        });
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['processed_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });
        Schema::table('store_api_keys', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });
        Schema::table('exports', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });
    }
};
