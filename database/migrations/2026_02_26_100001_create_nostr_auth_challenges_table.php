<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nostr_auth_challenges', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('nostr_public_key', 64)->nullable();
            $table->foreignId('link_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('purpose', 32)->nullable();
            $table->foreignId('pending_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nostr_auth_challenges');
    }
};
