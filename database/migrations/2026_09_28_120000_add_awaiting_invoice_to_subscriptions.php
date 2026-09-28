<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a paid subscription row created from a payment signal (PlanStarted
     * webhook, BTCPay reconcile) before its settled invoice was applied. Only
     * such a row may be claimed by that invoice instead of being extended.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('awaiting_invoice')->default(false)->after('btcpay_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('awaiting_invoice');
        });
    }
};
