<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_passkey_envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // WebAuthn credential id (base64url) - globally unique, high-entropy.
            $table->string('credential_id', 512)->unique();
            $table->string('label', 100)->nullable();
            // AES-256-GCM ciphertext of the recovery phrase, encrypted client-side
            // with a PRF-derived key. The server can never decrypt it.
            $table->text('payload');
            $table->unsignedSmallInteger('envelope_version')->default(1);
            $table->json('transports')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_passkey_envelopes');
    }
};
