<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->string('filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);
            $table->unsignedInteger('auto_matched_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('bank_import_batch_id')->nullable()->constrained('bank_import_batches')->nullOnDelete();
            $table->timestamp('booked_at');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('direction', 16);
            $table->string('match_status', 16)->default('unmatched');
            $table->string('variable_symbol', 32)->nullable();
            $table->string('constant_symbol', 16)->nullable();
            $table->string('specific_symbol', 16)->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_iban', 64)->nullable();
            $table->text('reference')->nullable();
            $table->string('bank_transaction_id', 128)->nullable();
            $table->string('dedupe_hash', 64);
            $table->string('source', 32)->default('import');
            $table->timestamps();

            $table->unique(['company_id', 'dedupe_hash']);
            $table->index(['company_id', 'match_status', 'booked_at']);
            $table->index(['company_id', 'variable_symbol']);
        });

        Schema::create('bank_transaction_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignUuid('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->decimal('matched_amount', 14, 2);
            $table->string('match_type', 16);
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['bank_transaction_id']);
            $table->index('business_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_matches');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_import_batches');
    }
};
