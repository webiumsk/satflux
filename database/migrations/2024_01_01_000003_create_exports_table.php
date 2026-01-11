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
        // Create enum type for export format
        DB::statement("CREATE TYPE export_format_enum AS ENUM ('standard', 'accounting')");

        // Create enum type for export status
        DB::statement("CREATE TYPE export_status_enum AS ENUM ('pending', 'running', 'finished', 'failed')");

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('format', ['standard', 'accounting']);
            $table->enum('status', ['pending', 'running', 'finished', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->json('filters')->nullable();
            $table->string('signed_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
        DB::statement('DROP TYPE IF EXISTS export_format_enum');
        DB::statement('DROP TYPE IF EXISTS export_status_enum');
    }
};

