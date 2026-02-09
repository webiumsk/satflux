<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('auto_report_enabled')->default(false)->after('metadata');
            $table->string('auto_report_email')->nullable()->after('auto_report_enabled');
            $table->string('auto_report_format')->default('standard')->after('auto_report_email'); // standard | xlsx
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['auto_report_enabled', 'auto_report_email', 'auto_report_format']);
        });
    }
};
