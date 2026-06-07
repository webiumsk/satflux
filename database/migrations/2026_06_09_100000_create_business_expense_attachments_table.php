<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expense_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_expense_id')->constrained('business_expenses')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        DB::table('business_expenses')
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', '')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                $now = now();
                foreach ($rows as $row) {
                    DB::table('business_expense_attachments')->insert([
                        'id' => (string) Str::uuid(),
                        'business_expense_id' => $row->id,
                        'disk' => $row->attachment_disk ?? 'local',
                        'path' => $row->attachment_path,
                        'original_filename' => $row->original_filename,
                        'mime' => $row->attachment_mime,
                        'size_bytes' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expense_attachments');
    }
};
