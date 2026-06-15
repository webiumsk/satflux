<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ephemeral_efaktura_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bridge_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('evolu_document_id', 64);
            $table->string('provider', 32)->default('peppol');
            $table->string('status', 32);
            $table->string('external_id', 128)->nullable();
            $table->text('message')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'evolu_document_id']);
            $table->unique(['user_id', 'evolu_document_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ephemeral_efaktura_submissions');
    }
};
