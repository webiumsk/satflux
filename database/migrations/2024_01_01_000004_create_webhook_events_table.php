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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload');
            $table->boolean('verified')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('event_type');
            $table->index('verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

