<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if column already exists (added by later migration)
        if (Schema::hasColumn('users', 'btcpay_user_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('btcpay_user_id')->nullable()->after('lightning_public_key');
            $table->index('btcpay_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'btcpay_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['btcpay_user_id']);
                $table->dropColumn('btcpay_user_id');
            });
        }
    }
};
