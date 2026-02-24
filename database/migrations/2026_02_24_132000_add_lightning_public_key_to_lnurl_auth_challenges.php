<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Store verified Lightning public key when user does not exist yet;
     * user is created in completeRegistration() after email is validated (unique in DB + BTCPay).
     */
    public function up(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->string('lightning_public_key', 128)->nullable()->after('consumed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lnurl_auth_challenges', function (Blueprint $table) {
            $table->dropColumn('lightning_public_key');
        });
    }
};
