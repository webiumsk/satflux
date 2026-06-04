<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_document_compliance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('status', 32)->default('pending');
            $table->string('external_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('qr_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_document_id', 'provider']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_document_compliance');
    }
};
