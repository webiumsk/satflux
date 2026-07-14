<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_auto_issue_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained()->cascadeOnDelete();
            // Company invoice-header snapshot (EphemeralSnapshotPayload.company
            // shape) synced from the local-first client so the server can
            // render and email documents headlessly. Email settings are NOT
            // duplicated here - they persist on the Company row (encrypted).
            $table->json('profile_json');
            $table->boolean('auto_email')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_auto_issue_profiles');
    }
};
