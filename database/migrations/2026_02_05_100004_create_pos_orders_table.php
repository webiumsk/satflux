<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * paid_method: lightning, onchain, cash, card. cash/card = offline settlement.
     */
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('pos_terminal_id');
            $table->foreignUuid('store_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 18, 8);
            $table->string('currency', 10)->default('EUR');
            $table->string('status', 32)->default('pending');
            $table->enum('paid_method', ['lightning', 'onchain', 'cash', 'card']);
            $table->string('btcpay_invoice_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->timestamp('paid_at')->nullable();

            $table->foreign('pos_terminal_id')->references('id')->on('pos_terminals')->onDelete('cascade');
            $table->index('store_id');
            $table->index('pos_terminal_id');
            $table->index('paid_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
