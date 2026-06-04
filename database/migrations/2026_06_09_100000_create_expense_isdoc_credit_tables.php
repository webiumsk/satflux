<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_isdoc_credit_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_isdoc_pack_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits');
            $table->decimal('price_eur', 10, 2);
            $table->string('btcpay_invoice_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('btcpay_invoice_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_isdoc_pack_purchases');
        Schema::dropIfExists('expense_isdoc_credit_balances');
    }
};
