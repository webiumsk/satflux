<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_configuration_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('connection_type');
            $table->text('encrypted_secret')->nullable();
            $table->string('status')->default('applying');
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_configuration_attempts');
    }
};
