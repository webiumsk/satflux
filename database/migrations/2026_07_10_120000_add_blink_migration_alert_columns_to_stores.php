<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('blink_alert_snoozed_until')->nullable()->after('wallet_type');
            $table->timestamp('blink_alert_dismissed_at')->nullable()->after('blink_alert_snoozed_until');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['blink_alert_snoozed_until', 'blink_alert_dismissed_at']);
        });
    }
};
