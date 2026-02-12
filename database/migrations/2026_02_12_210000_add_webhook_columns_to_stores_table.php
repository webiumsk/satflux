<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('auto_report_format');
            $table->string('btcpay_webhook_id')->nullable()->after('webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['webhook_secret', 'btcpay_webhook_id']);
        });
    }
};
