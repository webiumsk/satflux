<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'year']);
        });

        Schema::create('business_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('company_contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('type', 32)->default('invoice');
            $table->string('status', 32)->default('draft');
            $table->string('number')->nullable();
            $table->string('title')->nullable();
            $table->string('variable_symbol')->nullable();
            $table->string('constant_symbol')->nullable();
            $table->string('specific_symbol')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('note_above_lines')->nullable();
            $table->text('note_footer')->nullable();
            $table->json('tags')->nullable();
            $table->string('btcpay_invoice_id')->nullable();
            $table->string('btcpay_checkout_link')->nullable();
            $table->boolean('payment_btc_enabled')->default(false);
            $table->boolean('payment_bank_enabled')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'type', 'status']);
            $table->unique(['company_id', 'number']);
        });

        Schema::create('business_document_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->string('unit', 32)->default('pcs');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_document_lines');
        Schema::dropIfExists('business_documents');
        Schema::dropIfExists('company_document_sequences');
    }
};
