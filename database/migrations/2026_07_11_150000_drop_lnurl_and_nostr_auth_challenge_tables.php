<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LNURL/Nostr login has been removed (product decision 2026-07-11). The challenge
 * tables held only ephemeral auth challenges - no user data is lost. The
 * users.lightning_public_key / users.nostr_public_key columns are deliberately
 * kept so previously linked identities remain recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('lnurl_auth_challenges');
        Schema::dropIfExists('nostr_auth_challenges');
    }

    public function down(): void
    {
        Schema::create('lnurl_auth_challenges', function (Blueprint $table) {
            $table->string('k1', 64)->primary();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('pending_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('lightning_public_key', 128)->nullable();
            $table->foreignId('link_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('purpose', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

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
};
