<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop existing check constraint
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

            // Create new check constraint with all valid roles including 'pro' and 'enterprise'
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('merchant', 'support', 'admin', 'pro', 'enterprise'))");
        }
        // For MySQL/MariaDB, the enum() method already handles this via the previous migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop the new constraint
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

            // Restore old constraint (without pro and enterprise)
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('merchant', 'support', 'admin'))");
        }
    }
};
