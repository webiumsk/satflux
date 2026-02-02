<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow 'nwc' in stores.wallet_type (PostgreSQL check constraint).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (wallet_type IS NULL OR wallet_type::text = ANY (ARRAY['blink', 'aqua_boltz', 'nwc']::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_wallet_type_check');
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_wallet_type_check CHECK (wallet_type IS NULL OR wallet_type::text = ANY (ARRAY['blink', 'aqua_boltz']::text[]))");
    }
};
