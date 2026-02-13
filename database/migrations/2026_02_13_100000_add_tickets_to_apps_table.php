<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL uses CHECK constraints for enum-like columns
            DB::statement("ALTER TABLE apps DROP CONSTRAINT IF EXISTS apps_app_type_check");
            DB::statement("ALTER TABLE apps ADD CONSTRAINT apps_app_type_check CHECK (app_type IN ('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress', 'Tickets'))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE apps MODIFY COLUMN app_type ENUM('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress', 'Tickets') NOT NULL");
        }
        // SQLite doesn't enforce enums strictly, no action needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE apps DROP CONSTRAINT IF EXISTS apps_app_type_check");
            DB::statement("ALTER TABLE apps ADD CONSTRAINT apps_app_type_check CHECK (app_type IN ('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress'))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE apps MODIFY COLUMN app_type ENUM('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress') NOT NULL");
        }
    }
};
