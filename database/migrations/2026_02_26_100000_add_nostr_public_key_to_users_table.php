<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'nostr_public_key')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('nostr_public_key', 64)->nullable()->unique()->after('lightning_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nostr_public_key');
        });
    }
};
