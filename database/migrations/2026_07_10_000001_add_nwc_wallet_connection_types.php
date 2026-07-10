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
            Schema::table('wallet_connections', function (Blueprint $table) {
                $table->dropColumn('type');
            });
            Schema::table('wallet_connections', function (Blueprint $table) {
                $table->string('type');
            });

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_connections DROP CONSTRAINT IF EXISTS wallet_connections_type_check');
            DB::statement("ALTER TABLE wallet_connections ADD CONSTRAINT wallet_connections_type_check CHECK (
                type::text = ANY (ARRAY['blink'::text, 'aqua_descriptor'::text, 'nwc'::text])
            )");

            DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
            DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (
                wallet_type IS NULL OR (wallet_type::text = ANY (ARRAY['blink'::text, 'aqua_boltz'::text, 'nwc'::text, 'cashu'::text]))
            )");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `wallet_connections` MODIFY COLUMN `type` ENUM('blink', 'aqua_descriptor', 'nwc') NOT NULL");
            DB::statement("ALTER TABLE `stores` MODIFY COLUMN `wallet_type` ENUM('blink', 'aqua_boltz', 'nwc', 'cashu') NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_connections DROP CONSTRAINT IF EXISTS wallet_connections_type_check');
            DB::statement("ALTER TABLE wallet_connections ADD CONSTRAINT wallet_connections_type_check CHECK (
                type::text = ANY (ARRAY['blink'::text, 'aqua_descriptor'::text])
            )");

            DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
            DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (
                wallet_type IS NULL OR (wallet_type::text = ANY (ARRAY['blink'::text, 'aqua_boltz'::text, 'nwc'::text, 'cashu'::text]))
            )");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `wallet_connections` MODIFY COLUMN `type` ENUM('blink', 'aqua_descriptor') NOT NULL");
            DB::statement("ALTER TABLE `stores` MODIFY COLUMN `wallet_type` ENUM('blink', 'aqua_boltz', 'cashu') NULL");
        }
    }
};
