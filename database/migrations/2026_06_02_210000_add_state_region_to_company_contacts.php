<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->string('state_region', 64)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->dropColumn('state_region');
        });
    }
};
