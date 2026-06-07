<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_credit_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 16)->default('SATS');
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after')->nullable();
            $table->string('description');
            $table->string('source_key')->nullable()->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_credit_ledger_entries');
    }
};
