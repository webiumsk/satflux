<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['faq_categories', 'faq_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('content_sync_key')->nullable()->unique();
                $table->string('content_sync_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['faq_categories', 'faq_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['content_sync_key']);
                $table->dropColumn(['content_sync_key', 'content_sync_hash']);
            });
        }
    }
};
