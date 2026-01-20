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
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Find existing enum type and create new one with additional values
            $enumType = DB::selectOne("
                SELECT t.typname 
                FROM pg_type t 
                JOIN pg_attribute a ON a.atttypid = t.oid 
                JOIN pg_class c ON c.oid = a.attrelid 
                WHERE c.relname = 'users' 
                AND a.attname = 'role' 
                AND t.typtype = 'e'
            ");
            
            if ($enumType && isset($enumType->typname)) {
                $oldTypeName = $enumType->typname;
                $newTypeName = $oldTypeName . '_new';
                
                // Create new enum type with all values
                DB::statement("CREATE TYPE {$newTypeName} AS ENUM ('merchant', 'support', 'admin', 'pro', 'enterprise')");
                
                // Alter column to use new type
                DB::statement("ALTER TABLE users ALTER COLUMN role TYPE {$newTypeName} USING role::text::{$newTypeName}");
                
                // Set default
                DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'merchant'");
                
                // Drop old type and rename new one
                DB::statement("DROP TYPE IF EXISTS {$oldTypeName} CASCADE");
                DB::statement("ALTER TYPE {$newTypeName} RENAME TO {$oldTypeName}");
            } else {
                // Fallback: Create type if it doesn't exist
                DB::statement("CREATE TYPE users_role_enum AS ENUM ('merchant', 'support', 'admin', 'pro', 'enterprise')");
                DB::statement("ALTER TABLE users ALTER COLUMN role TYPE users_role_enum USING role::text::users_role_enum");
                DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'merchant'");
            }
        } else {
            // MySQL/MariaDB syntax
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('merchant', 'support', 'admin', 'pro', 'enterprise') DEFAULT 'merchant'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // Before reverting, set any 'pro' or 'enterprise' users back to 'merchant'
        DB::table('users')
            ->whereIn('role', ['pro', 'enterprise'])
            ->update(['role' => 'merchant']);
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Cannot remove enum values, so we just ensure no users have those roles
            // The enum values will remain but won't be used
            // Note: PostgreSQL doesn't support removing enum values without recreating the type
            // This is a limitation - enum values remain in the database but are unused
        } else {
            // MySQL/MariaDB syntax
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('merchant', 'support', 'admin') DEFAULT 'merchant'");
        }
    }
};
