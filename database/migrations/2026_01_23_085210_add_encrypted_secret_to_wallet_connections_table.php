<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // We're using the original column name 'secret_encrypted' in the code
        // So we just need to ensure it exists. If it doesn't, add it.
        if (!Schema::hasColumn('wallet_connections', 'secret_encrypted')) {
            Schema::table('wallet_connections', function (Blueprint $table) {
                // Add as nullable first to avoid NOT NULL violation on existing rows
                $table->text('secret_encrypted')->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        // Only drop if it was added by this migration (not if it existed before)
        // For safety, we'll leave it - this migration is idempotent
    }
};
