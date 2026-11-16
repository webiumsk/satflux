<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_document_payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('store_company_id')->nullable();
            $table->string('document_key', 70);
            $table->string('evolu_document_id', 64)->nullable();
            $table->foreignUuid('business_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->text('terms');
            $table->string('fingerprint', 64);
            $table->string('state', 16)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->string('payment_method', 16)->nullable();
            $table->uuid('attempt_id')->nullable();
            $table->string('btcpay_invoice_id', 128)->nullable();
            $table->string('settled_invoice_id', 128)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'document_key']);
        });
        Schema::create('business_document_payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('intent_id')->constrained('business_document_payment_intents')->cascadeOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('store_company_id')->nullable();
            $table->string('btcpay_invoice_id', 128)->nullable();
            $table->text('terms');
            $table->string('state', 16);
            $table->timestamps();
            $table->unique(['store_id', 'btcpay_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_document_payment_attempts');
        Schema::dropIfExists('business_document_payment_intents');
    }
};
