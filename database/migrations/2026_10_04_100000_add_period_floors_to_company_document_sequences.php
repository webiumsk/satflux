<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter floor per numbering period. A series row holds one period at a
 * time; around midnight UTC the client's period date (+-1 day) can switch
 * it back and forth, and every switch reset the stored counter to 0 -
 * losing a manually entered "last used" value of the period switched away
 * from. The floor of the period being left is kept here and restored on
 * the way back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->json('period_floors')->nullable()->after('last_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->dropColumn('period_floors');
        });
    }
};
