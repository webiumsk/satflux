<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->text('internal_note')->nullable()->after('note_footer');
            $table->string('pdf_locale', 8)->nullable()->after('internal_note');
            $table->boolean('pdf_show_signature')->default(true)->after('pdf_locale');
            $table->boolean('pdf_show_payment_info')->default(true)->after('pdf_show_signature');
            $table->timestamp('paid_at')->nullable()->after('pdf_show_payment_info');
            $table->decimal('amount_paid', 14, 2)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropColumn([
                'internal_note',
                'pdf_locale',
                'pdf_show_signature',
                'pdf_show_payment_info',
                'paid_at',
                'amount_paid',
            ]);
        });
    }
};
