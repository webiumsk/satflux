<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boltz_service_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 64);
            $table->string('locale', 2);
            $table->string('status', 24);
            $table->timestamp('claimed_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'campaign']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boltz_service_notifications');
    }
};
