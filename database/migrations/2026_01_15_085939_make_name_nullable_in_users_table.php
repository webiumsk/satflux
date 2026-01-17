<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make name column nullable
        DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Make name column NOT NULL again (set existing NULL values to empty string first)
        DB::statement('UPDATE users SET name = \'\' WHERE name IS NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN name SET NOT NULL');
    }
};
