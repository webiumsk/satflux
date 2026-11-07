<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add an optional canonical list without changing existing relay selections. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('evolu_relays')->nullable();
        });
    }

    /** Return to the existing legacy relay fields. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('evolu_relays');
        });
    }
};
