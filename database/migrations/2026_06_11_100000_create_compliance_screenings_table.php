<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_screenings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 32);
            $table->string('subject_email');
            $table->string('subject_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('geo_blocked')->default(false);
            $table->string('screening_provider', 64);
            $table->string('screening_status', 16);
            $table->string('screening_reference')->nullable();
            $table->string('screening_payload_hash', 64)->nullable();
            $table->string('decision', 16);
            $table->string('decision_reason', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('subject_email');
            $table->index('user_id');
            $table->index('decision');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_screenings');
    }
};
