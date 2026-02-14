<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable()->after('auto_report_format');
            }
            if (!Schema::hasColumn('stores', 'btcpay_webhook_id')) {
                $table->string('btcpay_webhook_id')->nullable()->after('webhook_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $columns = array_filter(
                ['webhook_secret', 'btcpay_webhook_id'],
                fn (string $col) => Schema::hasColumn('stores', $col)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
