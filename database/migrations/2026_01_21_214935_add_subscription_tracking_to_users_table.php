<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('btcpay_subscription_id')->nullable()->after('role');
            $table->timestamp('subscription_expires_at')->nullable()->after('btcpay_subscription_id');
            $table->timestamp('subscription_grace_period_ends_at')->nullable()->after('subscription_expires_at');

            $table->index('btcpay_subscription_id');
            $table->index('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['btcpay_subscription_id']);
            $table->dropIndex(['subscription_expires_at']);

            $table->dropColumn([
                'btcpay_subscription_id',
                'subscription_expires_at',
                'subscription_grace_period_ends_at',
            ]);
        });
    }
};
