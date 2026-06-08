<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->string('peppol_participant_id', 64)->nullable()->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->dropColumn('peppol_participant_id');
        });
    }
};
