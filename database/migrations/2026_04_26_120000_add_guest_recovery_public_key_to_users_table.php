<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('guest_recovery_public_key', 64)->nullable()->unique()->after('is_guest');
            $table->timestamp('guest_recovery_enrolled_at')->nullable()->after('guest_recovery_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['guest_recovery_public_key', 'guest_recovery_enrolled_at']);
        });
    }
};
