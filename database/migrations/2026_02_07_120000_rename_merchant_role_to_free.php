<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Unify free tier: rename role 'merchant' to 'free' so plan level and role match.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: column uses native ENUM type; we must create a new enum with 'free'
            // and convert the column (merchant -> free) in one ALTER USING.
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ALTER COLUMN role DROP DEFAULT");

            $enumType = DB::selectOne("
                SELECT t.typname
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'users'
                AND a.attname = 'role'
                AND t.typtype = 'e'
                AND a.attnum > 0
                AND NOT a.attisdropped
            ");

            $oldTypeName = $enumType->typname ?? 'users_role_enum';
            $newTypeName = $oldTypeName . '_free';

            DB::statement("CREATE TYPE {$newTypeName} AS ENUM ('free', 'support', 'admin', 'pro', 'enterprise')");
            DB::statement("ALTER TABLE users ALTER COLUMN role TYPE {$newTypeName} USING (CASE WHEN role::text = 'merchant' OR role IS NULL THEN 'free'::{$newTypeName} ELSE role::text::{$newTypeName} END)");
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'free'");
            DB::statement("DROP TYPE {$oldTypeName}");
            DB::statement("ALTER TYPE {$newTypeName} RENAME TO {$oldTypeName}");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("UPDATE users SET role = 'free' WHERE role = 'merchant' OR role IS NULL");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('free', 'support', 'admin', 'pro', 'enterprise') DEFAULT 'free'");
        } else {
            DB::statement("UPDATE users SET role = 'free' WHERE role = 'merchant' OR role IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN role DROP DEFAULT");

            $enumType = DB::selectOne("
                SELECT t.typname
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'users'
                AND a.attname = 'role'
                AND t.typtype = 'e'
                AND a.attnum > 0
                AND NOT a.attisdropped
            ");

            $oldTypeName = $enumType->typname ?? 'users_role_enum';
            $newTypeName = $oldTypeName . '_merchant';

            DB::statement("CREATE TYPE {$newTypeName} AS ENUM ('merchant', 'support', 'admin', 'pro', 'enterprise')");
            DB::statement("ALTER TABLE users ALTER COLUMN role TYPE {$newTypeName} USING (CASE WHEN role::text = 'free' THEN 'merchant'::{$newTypeName} ELSE role::text::{$newTypeName} END)");
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'merchant'");
            DB::statement("DROP TYPE {$oldTypeName}");
            DB::statement("ALTER TYPE {$newTypeName} RENAME TO {$oldTypeName}");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("UPDATE users SET role = 'merchant' WHERE role = 'free'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('merchant', 'support', 'admin', 'pro', 'enterprise') DEFAULT 'merchant'");
        } else {
            DB::statement("UPDATE users SET role = 'merchant' WHERE role = 'free'");
        }
    }
};
