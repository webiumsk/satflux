<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source', 32);
            $table->string('external_id', 64);
            $table->string('primary_name');
            $table->string('primary_name_normalized');
            $table->json('aliases_normalized');
            $table->json('countries')->nullable();
            $table->timestamp('synced_at');

            $table->unique(['source', 'external_id']);
            $table->index('primary_name_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_entries');
    }
};
