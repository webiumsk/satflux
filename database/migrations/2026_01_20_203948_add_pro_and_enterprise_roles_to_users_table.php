<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL/MariaDB, we need to modify the enum directly
        $tableName = 'users';
        $columnName = 'role';

        // Modify enum to include new roles
        DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` ENUM('merchant', 'support', 'admin', 'pro', 'enterprise') DEFAULT 'merchant'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'users';
        $columnName = 'role';

        // Before reverting, set any 'pro' or 'enterprise' users back to 'merchant'
        DB::table('users')
            ->whereIn('role', ['pro', 'enterprise'])
            ->update(['role' => 'merchant']);

        // Modify enum to remove new roles
        DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` ENUM('merchant', 'support', 'admin') DEFAULT 'merchant'");
    }
};
