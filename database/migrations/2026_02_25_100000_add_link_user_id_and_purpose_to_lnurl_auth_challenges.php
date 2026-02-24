<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * link_user_id: when set, challenge is for linking Lightning key to this user (profile) or for reveal confirm.
     * purpose: 'link' = add Lightning login to account; 'reveal' = confirm wallet-connection reveal via LNURL.
     */
    public function up(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->foreignId('link_user_id')->nullable()->after('lightning_public_key')->constrained('users')->onDelete('cascade');
            $table->string('purpose', 32)->nullable()->after('link_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->dropForeign(['link_user_id']);
            $table->dropColumn(['link_user_id', 'purpose']);
        });
    }
};
