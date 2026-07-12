<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periodic system health snapshots (P1 phase 8). One row per scheduled
 * system:health-check run; pruned by the command after the configured
 * retention. Check details are short non-sensitive strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->boolean('healthy');
            $table->json('checks');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_snapshots');
    }
};
