<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->index(['status', 'cancelled_at']);
        });

        DB::table('business_expenses')
            ->where('status', 'cancelled')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropIndex(['status', 'cancelled_at']);
            $table->dropColumn('cancelled_at');
        });
    }
};
