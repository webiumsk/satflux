<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('bank_account', 64)->nullable()->after('bank_name');
            $table->string('bank_code', 16)->nullable()->after('bank_account');
            $table->string('logo_path')->nullable()->after('invoice_number_prefix');
            $table->string('signature_stamp_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['bank_account', 'bank_code', 'logo_path', 'signature_stamp_path']);
        });
    }
};
