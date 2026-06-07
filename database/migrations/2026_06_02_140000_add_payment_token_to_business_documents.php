<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->string('payment_token', 64)->nullable()->unique()->after('btcpay_checkout_link');
            $table->timestamp('btcpay_checkout_created_at')->nullable()->after('payment_token');
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'btcpay_checkout_created_at']);
        });
    }
};
