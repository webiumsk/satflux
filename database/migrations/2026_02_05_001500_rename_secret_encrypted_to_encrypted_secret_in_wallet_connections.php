<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Code uses column "encrypted_secret". DBs created with the old migration have "secret_encrypted".
 * Rename so code and DB match (no change on production if it already has encrypted_secret).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_connections')) {
            return;
        }

        if (Schema::hasColumn('wallet_connections', 'secret_encrypted') &&
            ! Schema::hasColumn('wallet_connections', 'encrypted_secret')) {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE wallet_connections RENAME COLUMN secret_encrypted TO encrypted_secret');
            } elseif ($driver === 'sqlite') {
                DB::statement('ALTER TABLE wallet_connections RENAME COLUMN secret_encrypted TO encrypted_secret');
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE wallet_connections CHANGE secret_encrypted encrypted_secret TEXT');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_connections')) {
            return;
        }

        if (Schema::hasColumn('wallet_connections', 'encrypted_secret') &&
            ! Schema::hasColumn('wallet_connections', 'secret_encrypted')) {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE wallet_connections RENAME COLUMN encrypted_secret TO secret_encrypted');
            } elseif ($driver === 'sqlite') {
                DB::statement('ALTER TABLE wallet_connections RENAME COLUMN encrypted_secret TO secret_encrypted');
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE wallet_connections CHANGE encrypted_secret secret_encrypted TEXT');
            }
        }
    }
};
