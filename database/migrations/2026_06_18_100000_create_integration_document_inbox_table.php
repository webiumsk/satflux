<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_document_inbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_integration_id')->constrained('store_integrations')->cascadeOnDelete();
            $table->unsignedBigInteger('woocommerce_order_id')->nullable();
            $table->uuid('evolu_document_id');
            $table->json('payload_json');
            $table->string('status', 16)->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_integration_id', 'status']);
            $table->unique(['store_integration_id', 'woocommerce_order_id'], 'integration_inbox_wc_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_document_inbox');
    }
};
