<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'default_currency')) {
                $table->string('default_currency', 10)->default('EUR')->after('name');
            }
            if (!Schema::hasColumn('stores', 'timezone')) {
                $table->string('timezone')->default('UTC')->after('default_currency');
            }
            if (!Schema::hasColumn('stores', 'preferred_exchange')) {
                $table->string('preferred_exchange')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('stores', 'wallet_type')) {
                $table->enum('wallet_type', ['blink', 'aqua_boltz'])->nullable()->after('preferred_exchange');
            }
            if (!Schema::hasColumn('stores', 'metadata')) {
                $table->json('metadata')->nullable()->after('wallet_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $columns = ['metadata', 'wallet_type', 'preferred_exchange', 'timezone', 'default_currency'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('stores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
