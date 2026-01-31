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
        Schema::create('wallet_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->enum('type', ['blink', 'aqua_descriptor']);
            $table->text('secret_encrypted');
            $table->enum('status', ['pending', 'needs_support', 'connected'])->default('pending');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('revealed_last_at')->nullable();
            $table->foreignId('revealed_last_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index('store_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_connections');
    }
};
