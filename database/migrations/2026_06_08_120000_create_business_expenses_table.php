<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 32)->default('recorded');
            $table->string('internal_number');
            $table->string('external_number')->nullable();
            $table->string('title')->nullable();
            $table->string('variable_symbol')->nullable();
            $table->string('constant_symbol')->nullable();
            $table->string('specific_symbol')->nullable();
            $table->date('issue_date');
            $table->date('delivery_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->text('internal_note')->nullable();
            $table->string('attachment_disk')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'internal_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
