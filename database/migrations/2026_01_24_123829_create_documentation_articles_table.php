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
        Schema::create('documentation_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->json('title'); // {en: "...", sk: "...", es: "..."}
            $table->json('content'); // {en: "...", sk: "...", es: "..."}
            $table->foreignUuid('category_id')->nullable()->constrained('documentation_categories')->onDelete('set null');
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->json('meta_description')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_published');
            $table->index(['category_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_articles');
    }
};
