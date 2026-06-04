<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->string('quote_status', 32)->nullable()->after('status');
            $table->index(['company_id', 'type', 'quote_status']);
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'type', 'quote_status']);
            $table->dropColumn('quote_status');
        });
    }
};
