<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->foreignUuid('company_contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_iban', 34)->nullable();
            $table->boolean('payment_review_required')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropForeign(['company_contact_id']);
            $table->dropColumn(['company_contact_id', 'supplier_name', 'supplier_iban', 'payment_review_required']);
        });
    }
};
