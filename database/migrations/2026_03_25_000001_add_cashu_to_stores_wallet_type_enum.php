<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Tests use sqlite in-memory; Laravel's enum maps to a CHECK that only allows blink/aqua_boltz.
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('wallet_type');
            });
            Schema::table('stores', function (Blueprint $table) {
                $table->string('wallet_type')->nullable();
            });

            return;
        }

        if ($driver === 'pgsql') {
            $enumType = DB::selectOne("
                SELECT t.typname
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'stores'
                  AND a.attname = 'wallet_type'
                  AND t.typtype = 'e'
                  AND a.attnum > 0
                  AND NOT a.attisdropped
            ");

            if ($enumType) {
                $type = '"'.str_replace('"', '""', $enumType->typname).'"';
                DB::statement("ALTER TYPE {$type} ADD VALUE IF NOT EXISTS 'cashu'");

                return;
            }

            // Laravel on PostgreSQL often uses varchar + CHECK, not a native ENUM type.
            DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
            DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (
                wallet_type IS NULL OR (wallet_type::text = ANY (ARRAY['blink'::text, 'aqua_boltz'::text, 'nwc'::text, 'cashu'::text]))
            )");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `stores` MODIFY COLUMN `wallet_type` ENUM('blink', 'aqua_boltz', 'cashu') NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        DB::table('stores')->where('wallet_type', 'cashu')->update(['wallet_type' => 'blink']);

        if ($driver === 'sqlite') {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('wallet_type');
            });
            Schema::table('stores', function (Blueprint $table) {
                $table->enum('wallet_type', ['blink', 'aqua_boltz'])->nullable();
            });

            return;
        }

        if ($driver === 'pgsql') {
            $enumType = DB::selectOne("
                SELECT t.typname
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'stores'
                  AND a.attname = 'wallet_type'
                  AND t.typtype = 'e'
                  AND a.attnum > 0
                  AND NOT a.attisdropped
            ");

            if (! $enumType) {
                DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
                DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (
                    wallet_type IS NULL OR (wallet_type::text = ANY (ARRAY['blink'::text, 'aqua_boltz'::text, 'nwc'::text]))
                )");

                return;
            }

            $oldTypeName = $enumType->typname;
            $newTypeName = $oldTypeName.'_new';

            DB::statement('ALTER TABLE stores ALTER COLUMN wallet_type DROP DEFAULT');

            DB::statement("CREATE TYPE {$newTypeName} AS ENUM ('blink', 'aqua_boltz')");
            DB::statement("ALTER TABLE stores ALTER COLUMN wallet_type TYPE {$newTypeName} USING wallet_type::text::{$newTypeName}");

            DB::statement("DROP TYPE IF EXISTS {$oldTypeName} CASCADE");
            DB::statement("ALTER TYPE {$newTypeName} RENAME TO {$oldTypeName}");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `stores` MODIFY COLUMN `wallet_type` ENUM('blink', 'aqua_boltz') NULL");
        }
    }
};
