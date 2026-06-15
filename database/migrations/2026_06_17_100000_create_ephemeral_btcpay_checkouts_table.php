<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ephemeral_btcpay_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('btcpay_invoice_id', 128);
            $table->string('evolu_document_id', 64);
            $table->string('status', 16)->default('pending');
            $table->decimal('amount', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'btcpay_invoice_id']);
            $table->index(['user_id', 'evolu_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ephemeral_btcpay_checkouts');
    }
};
