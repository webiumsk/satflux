<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Panel API keys (for our panel API), not BTCPay keys.
     */
    public function up(): void
    {
        Schema::create('user_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->timestamp('revoked_at')->nullable();

            $table->index('user_id');
            $table->index('key_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_keys');
    }
};
