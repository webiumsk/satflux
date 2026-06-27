<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compliance_screenings')) {
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

        if (! Schema::hasTable('sanctions_entries')) {
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

        if (
            Schema::hasTable('sanctions_entries')
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            $indexExists = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE indexname = 'sanctions_entries_aliases_normalized_gin'",
            );
            if ($indexExists === null) {
                DB::statement(
                    'CREATE INDEX sanctions_entries_aliases_normalized_gin ON sanctions_entries USING GIN ((aliases_normalized::jsonb))',
                );
            }
        }
    }

    public function down(): void
    {
        // No-op: tables may predate this migration.
    }
};
