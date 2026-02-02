<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * NWC connections do not store the secret in Laravel (it is in the NWC Connector service).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE wallet_connections ALTER COLUMN secret_encrypted DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_connections ALTER COLUMN secret_encrypted SET NOT NULL');
    }
};
