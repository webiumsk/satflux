<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /** Build the PostgreSQL access path without blocking writes; other drivers retain the portable index. */
    public function up(): void
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'pgsql') {
            if ($connection->transactionLevel() !== 0) {
                throw new LogicException('Concurrent bank sync index creation requires an autocommit PostgreSQL connection.');
            }
            $connection->statement('CREATE INDEX CONCURRENTLY "bank_transactions_sync_order_idx" ON "bank_transactions" ("company_id", "source", "created_at", "id")');

            return;
        }
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->index(['company_id', 'source', 'created_at', 'id'], 'bank_transactions_sync_order_idx');
        });
    }

    /** Remove only this non-unique access path; retained transactions and other indexes remain untouched. */
    public function down(): void
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'pgsql') {
            if ($connection->transactionLevel() !== 0) {
                throw new LogicException('Concurrent bank sync index removal requires an autocommit PostgreSQL connection.');
            }
            $connection->statement('DROP INDEX CONCURRENTLY "bank_transactions_sync_order_idx"');

            return;
        }
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropIndex('bank_transactions_sync_order_idx');
        });
    }
};
