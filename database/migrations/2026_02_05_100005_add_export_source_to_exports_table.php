<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Distinguish manual vs automatic (monthly) exports.
     */
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('source', 32)->default('manual')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
