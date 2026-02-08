<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('wallet_connections', 'encrypted_secret')) {
            Schema::table('wallet_connections', function (Blueprint $table) {
                $table->text('encrypted_secret')->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        // Only drop if it was added by this migration (not if it existed before)
        // For safety, we'll leave it - this migration is idempotent
    }
};
