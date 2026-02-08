<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix empty encrypted_secret: copy from secret_encrypted if that column exists and has data.
 * Some DBs were created with secret_encrypted; add migration added encrypted_secret (empty).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_connections')) {
            return;
        }

        if (Schema::hasColumn('wallet_connections', 'secret_encrypted') &&
            Schema::hasColumn('wallet_connections', 'encrypted_secret')) {
            $driver = DB::getDriverName();
            $quotedSecret = $driver === 'pgsql' ? 'secret_encrypted' : '`secret_encrypted`';
            DB::statement("
                UPDATE wallet_connections
                SET encrypted_secret = {$quotedSecret}
                WHERE (encrypted_secret IS NULL OR encrypted_secret = '')
                  AND secret_encrypted IS NOT NULL
                  AND secret_encrypted != ''
            ");
        }
    }

    public function down(): void
    {
        // No revert - we don't want to clear data
    }
};
