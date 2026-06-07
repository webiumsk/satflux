<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_consent_at')->nullable()->after('email_verified_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('privacy_consent_at');
        });

        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('name', 120);
            $table->string('email');
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->timestamp('privacy_consent_at');
            $table->string('locale', 8)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_consent_at', 'terms_accepted_at']);
        });
    }
};
