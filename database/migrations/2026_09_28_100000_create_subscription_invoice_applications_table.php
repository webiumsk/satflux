<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per settled BTCPay subscription invoice that has granted paid
     * time. The unique invoice id makes activation idempotent across the
     * success redirect, webhooks and the manual fulfil command.
     */
    public function up(): void
    {
        Schema::create('subscription_invoice_applications', function (Blueprint $table) {
            $table->id();
            $table->string('btcpay_invoice_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan');
            $table->timestamps();

            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoice_applications');
    }
};
