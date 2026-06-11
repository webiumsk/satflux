<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_stock_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 32)->default('ks');
            $table->boolean('track_inventory')->default(true);
            $table->decimal('quantity_on_hand', 14, 4)->default(0);
            $table->decimal('purchase_unit_price', 14, 2)->nullable();
            $table->char('purchase_currency', 3)->nullable();
            $table->decimal('sale_unit_price', 14, 2)->nullable();
            $table->text('internal_note')->nullable();
            $table->boolean('exclude_from_suggester')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'sku']);
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('company_stock_item_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_stock_item_id')->constrained('company_stock_items')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->decimal('quantity_after', 14, 4);
            $table->decimal('quantity_delta', 14, 4);
            $table->decimal('purchase_unit_price', 14, 2)->nullable();
            $table->decimal('sale_unit_price', 14, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('source', 32);
            $table->foreignUuid('business_document_id')->nullable()->constrained('business_documents')->nullOnDelete();
            $table->string('document_number')->nullable();
            $table->string('document_type', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_stock_item_id', 'created_at']);
            $table->index(['business_document_id', 'source']);
        });

        Schema::table('business_document_lines', function (Blueprint $table) {
            $table->foreignUuid('company_stock_item_id')
                ->nullable()
                ->after('business_document_id')
                ->constrained('company_stock_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_stock_item_id');
        });

        Schema::dropIfExists('company_stock_item_movements');
        Schema::dropIfExists('company_stock_items');
    }
};
