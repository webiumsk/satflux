<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * bot_failure_message: last error from config bot (when status = needs_support).
     * bot_failed_at: when bot last reported failure.
     * secret_updated_at: when connection string was last changed by a user (not by bot).
     */
    public function up(): void
    {
        Schema::table('wallet_connections', function (Blueprint $table) {
            $table->text('bot_failure_message')->nullable()->after('reconfig');
            $table->timestamp('bot_failed_at')->nullable()->after('bot_failure_message');
            $table->timestamp('secret_updated_at')->nullable()->after('bot_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_connections', function (Blueprint $table) {
            $table->dropColumn(['bot_failure_message', 'bot_failed_at', 'secret_updated_at']);
        });
    }
};
