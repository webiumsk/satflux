<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_phase', 20)->default('paid')->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('expires_at');
            $table->index('billing_phase');
            $table->index('trial_ends_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('trial_consumed_at')->nullable()->after('subscription_grace_period_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_phase']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropColumn(['billing_phase', 'trial_ends_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('trial_consumed_at');
        });
    }
};
