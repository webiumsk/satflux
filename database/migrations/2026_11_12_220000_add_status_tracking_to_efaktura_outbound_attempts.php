<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('efaktura_outbound_attempts', function (Blueprint $table) {
            // down() preserves custody; reapply only the missing schema parts.
            if (! Schema::hasColumn('efaktura_outbound_attempts', 'status_binding_encrypted')) {
                $table->text('status_binding_encrypted')->nullable();
            }
            if (! Schema::hasColumn('efaktura_outbound_attempts', 'status_last_attempt_at')) {
                $table->timestamp('status_last_attempt_at')->nullable();
            }
            if (! Schema::hasColumn('efaktura_outbound_attempts', 'status_next_check_at')) {
                $table->timestamp('status_next_check_at')->nullable();
            }
            if (! Schema::hasColumn('efaktura_outbound_attempts', 'status_failures')) {
                $table->unsignedInteger('status_failures')->default(0);
            }
            if (! Schema::hasColumn('efaktura_outbound_attempts', 'status_complete')) {
                $table->boolean('status_complete')->default(false);
            }
            if (! Schema::hasIndex('efaktura_outbound_attempts', 'efaktura_status_due_index')) {
                $table->index(['status_complete', 'status_next_check_at'], 'efaktura_status_due_index');
            }
        });
    }

    public function down(): void
    {
        // Retain immutable identity and scheduling evidence across rollback.
        // Older application versions ignore these additive nullable columns.
    }
};
