<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'Tickets' to apps.app_type enum so Satoshi Tickets apps can be created.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE apps MODIFY COLUMN app_type ENUM('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress', 'Tickets') NOT NULL");
        }
        // SQLite uses affinity and does not enforce enum; PostgreSQL would need ALTER TYPE.
    }

    /**
     * Reverse: remove 'Tickets' from enum (only if no Tickets apps exist).
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE apps MODIFY COLUMN app_type ENUM('PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress') NOT NULL");
        }
    }
};
