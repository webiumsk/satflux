<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create enum type for wallet_type
        DB::statement("CREATE TYPE wallet_type_enum AS ENUM ('blink', 'aqua_boltz')");

        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('btcpay_store_id');
            $table->string('name');
            $table->enum('wallet_type', ['blink', 'aqua_boltz'])->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'btcpay_store_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
        DB::statement('DROP TYPE IF EXISTS wallet_type_enum');
    }
};

