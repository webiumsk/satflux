<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * reconfig = true when merchant is updating an already-connected Lightning setup (different BTCPay UI flow).
     */
    public function up(): void
    {
        Schema::table('wallet_connections', function (Blueprint $table) {
            $table->boolean('reconfig')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_connections', function (Blueprint $table) {
            $table->dropColumn('reconfig');
        });
    }
};
