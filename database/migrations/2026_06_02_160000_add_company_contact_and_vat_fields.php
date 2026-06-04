<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vat_number', 32)->nullable()->after('tax_id');
            $table->string('vat_status', 16)->default('none')->after('vat_payer');
            $table->string('commercial_register', 512)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['vat_number', 'commercial_register']);
        });
    }
};
