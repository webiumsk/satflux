<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow 'nwc' in wallet_connections.type (PostgreSQL check constraint).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE wallet_connections DROP CONSTRAINT IF EXISTS wallet_connections_type_check');
        DB::statement("ALTER TABLE wallet_connections ADD CONSTRAINT wallet_connections_type_check CHECK (type::text = ANY (ARRAY['blink', 'aqua_descriptor', 'nwc']::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_connections DROP CONSTRAINT IF EXISTS wallet_connections_type_check');
        DB::statement("ALTER TABLE wallet_connections ADD CONSTRAINT wallet_connections_type_check CHECK (type::text = ANY (ARRAY['blink', 'aqua_descriptor']::text[]))");
    }
};
