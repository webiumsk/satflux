<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efaktura_inbound_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('external_document_id');
            $table->foreignUuid('business_expense_id')->nullable()->constrained('business_expenses')->nullOnDelete();
            $table->string('status', 32)->default('imported');
            $table->string('attachment_disk')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_document_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efaktura_inbound_receipts');
    }
};
