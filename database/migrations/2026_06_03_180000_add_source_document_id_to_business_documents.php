<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->foreignUuid('source_document_id')
                ->nullable()
                ->after('store_id')
                ->constrained('business_documents')
                ->nullOnDelete();
            $table->index('source_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropForeign(['source_document_id']);
            $table->dropIndex(['source_document_id']);
            $table->dropColumn('source_document_id');
        });
    }
};
