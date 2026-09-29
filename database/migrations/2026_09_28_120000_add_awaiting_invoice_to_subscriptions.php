<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a payment signal (PlanStarted webhook, BTCPay reconcile) created a
     * paid subscription row or converted a trial into one, before its settled
     * invoice was applied. Only such a row, marked after the invoice was
     * created, may be claimed by that invoice instead of being extended.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('awaiting_invoice_at')->nullable()->after('btcpay_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('awaiting_invoice_at');
        });
    }
};
