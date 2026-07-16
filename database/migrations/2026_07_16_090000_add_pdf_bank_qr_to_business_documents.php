<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            // Per-document bank QR choice: auto|paybysquare|epc|swiss|none;
            // null behaves like auto (payer-country matrix).
            $table->string('pdf_bank_qr', 16)->nullable()->after('pdf_locale');
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropColumn('pdf_bank_qr');
        });
    }
};
