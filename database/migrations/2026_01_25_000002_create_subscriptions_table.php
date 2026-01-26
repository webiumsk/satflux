<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates subscriptions table to track user subscriptions.
     * Status values: 'active', 'grace', 'expired'
     * 
     * IMPORTANT: This is a non-custodial system. Expired subscriptions
     * do NOT affect payment acceptance - only management features are limited.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->enum('status', ['active', 'grace', 'expired'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('btcpay_subscription_id')->nullable(); // BTCPay subscription ID
            $table->timestamps();

            $table->index('user_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('btcpay_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
