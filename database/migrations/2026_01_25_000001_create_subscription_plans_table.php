<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates subscription_plans table to store plan definitions.
     * Plans define limits and features for each tier (FREE, PRO, ENTERPRISE).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'free', 'pro', 'enterprise'
            $table->string('display_name'); // 'Free', 'Pro', 'Enterprise'
            $table->decimal('price_eur', 10, 2)->default(0); // Price in EUR per year
            $table->integer('max_stores')->nullable(); // null = unlimited
            $table->integer('max_api_keys')->nullable(); // null = unlimited
            $table->json('features')->nullable(); // Feature flags as JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
