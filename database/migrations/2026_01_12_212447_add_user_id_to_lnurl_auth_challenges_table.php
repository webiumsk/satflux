<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->foreignId('pending_user_id')->nullable()->after('consumed_at')->constrained('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->dropForeign(['pending_user_id']);
            $table->dropColumn('pending_user_id');
        });
    }
};
