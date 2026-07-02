<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            // BTCPay delivery ID - used to reject replayed webhook payloads.
            // Nullable: legacy rows and payloads without a deliveryId.
            $table->string('delivery_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique(['delivery_id']);
            $table->dropColumn('delivery_id');
        });
    }
};
