<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes used by queries: btcpay_store_id (stores), processed_at (webhook_events),
     * role (users), expires_at (lnurl_auth_challenges, store_api_keys, exports).
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->index('btcpay_store_id');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->index('processed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->index('expires_at');
        });

        Schema::table('store_api_keys', function (Blueprint $table) {
            $table->index('expires_at');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['btcpay_store_id']);
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['processed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });

        Schema::table('store_api_keys', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });
    }
};
