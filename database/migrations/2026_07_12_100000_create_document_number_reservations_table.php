<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side document number allocator (invoicing audit F3).
 *
 * Each row is one atomic number reservation for a company + document type,
 * keyed by a client-generated issue_request_id so a retried issue attempt
 * gets the SAME number back instead of burning a new one. Numbers are never
 * recycled: a voided reservation leaves a gap in the sequence.
 *
 * The server never stores invoice content here - only the counter, the
 * formatted number and (on confirm) an opaque snapshot hash + format version
 * supplied by the local-first client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 32);
            // Restrict, not cascade: reservations are the audit trail of
            // allocated numbers - deleting a sequence must not erase them.
            // The sequence destroy endpoint refuses deletion while
            // reservations exist.
            $table->foreignId('company_document_sequence_id')
                ->constrained('company_document_sequences')
                ->restrictOnDelete();
            $table->string('issue_request_id', 64);
            $table->string('period_key', 32)->nullable();
            $table->unsignedInteger('counter');
            $table->string('number', 64);
            $table->string('status', 16)->default('reserved');
            $table->string('confirmed_hash', 128)->nullable();
            $table->string('confirmed_format_version', 16)->nullable();
            $table->timestamps();

            // Idempotency: one reservation per issue attempt identity.
            $table->unique(
                ['company_id', 'document_type', 'issue_request_id'],
                'doc_number_reservations_request_unique',
            );
            $table->index(['company_id', 'status']);
            // applyReservedCounterFloor(): max(counter) per sequence + period.
            $table->index(
                ['company_document_sequence_id', 'period_key'],
                'doc_number_reservations_sequence_period_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_reservations');
    }
};
