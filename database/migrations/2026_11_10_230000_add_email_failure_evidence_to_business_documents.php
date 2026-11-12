<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table): void {
            $table->timestamp('email_failed_at')->nullable();
            $table->string('email_failure_code', 32)->nullable();
            $table->index(['company_id', 'email_failed_at'], 'business_documents_company_email_failure_idx');
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table): void {
            $table->dropIndex('business_documents_company_email_failure_idx');
            $table->dropColumn(['email_failed_at', 'email_failure_code']);
        });
    }
};
