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
        Schema::create('apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('btcpay_app_id'); // BTCPay app ID
            $table->enum('app_type', ['PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress']);
            $table->string('name');
            $table->json('config')->nullable(); // App-specific configuration
            $table->json('metadata')->nullable(); // Local metadata
            $table->timestamps();
            
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index('store_id');
            $table->unique(['store_id', 'btcpay_app_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
