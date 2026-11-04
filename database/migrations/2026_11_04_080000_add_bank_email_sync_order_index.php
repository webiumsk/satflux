<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Support backward scans within the fixed company/source prefix for both timestamp partitions. */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->index(['company_id', 'source', 'created_at', 'id'], 'bank_transactions_sync_order_idx');
        });
    }

    /** Remove only this non-unique access path; retained transactions and other indexes remain untouched. */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropIndex('bank_transactions_sync_order_idx');
        });
    }
};
