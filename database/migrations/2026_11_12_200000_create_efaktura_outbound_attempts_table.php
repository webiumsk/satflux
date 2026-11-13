<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efaktura_outbound_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_kind', 12);
            $table->string('source_document_id', 64);
            $table->char('connection_fingerprint', 64);
            $table->char('content_sha256', 64);
            $table->text('request_encrypted')->nullable();
            $table->string('status', 32);
            $table->string('external_id', 128)->nullable();
            $table->json('response_payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('retry_until');
            $table->timestamps();
            $table->unique(['user_id', 'source_kind', 'source_document_id'], 'efaktura_outbound_source_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('efaktura_outbound_attempts') && DB::table('efaktura_outbound_attempts')->exists()) {
            throw new RuntimeException('Retain ePostman outbound attempts during rollback. Reconcile submissions before removing their retry evidence.');
        }
        Schema::dropIfExists('efaktura_outbound_attempts');
    }
};
