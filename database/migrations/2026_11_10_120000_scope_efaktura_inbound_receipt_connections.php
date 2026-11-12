<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('efaktura_inbound_receipts', function (Blueprint $table) {
            // Unknown historical provenance is deliberately not guessed from current settings.
            $table->string('connection_key', 64)->default('legacy');
            $table->string('connection_origin')->nullable();
            $table->string('receiver_participant_id')->nullable();
            $table->string('original_sha256', 64)->nullable();
            $table->dropUnique(['company_id', 'external_document_id']);
            $table->unique(['company_id', 'connection_key', 'external_document_id'], 'efaktura_inbound_connection_document_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('efaktura_inbound_receipts')->select('company_id', 'external_document_id')->groupBy('company_id', 'external_document_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot discard receipt provenance while document IDs overlap across connections. Preserve data and roll forward.');
        }

        Schema::table('efaktura_inbound_receipts', function (Blueprint $table) {
            $table->dropUnique('efaktura_inbound_connection_document_unique');
            $table->unique(['company_id', 'external_document_id']);
            $table->dropColumn(['connection_key', 'connection_origin', 'receiver_participant_id', 'original_sha256']);
        });
    }
};
