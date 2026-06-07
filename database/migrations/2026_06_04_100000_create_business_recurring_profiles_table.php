<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_recurring_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('company_contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('document_type', 32);
            $table->boolean('is_active')->default(true);
            $table->string('recurrence_interval', 16);
            $table->date('first_issue_date');
            $table->date('next_issue_date');
            $table->boolean('repeat_indefinitely')->default(true);
            $table->date('ends_at')->nullable();
            $table->boolean('issue_last_day_of_month')->default(false);
            $table->string('title')->nullable();
            $table->string('variable_symbol', 32)->nullable();
            $table->string('constant_symbol', 10)->nullable();
            $table->string('specific_symbol', 10)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->string('delivery_date_mode', 16)->default('on_issue');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('note_above_lines')->nullable();
            $table->text('note_footer')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('pdf_locale', 8)->default('sk');
            $table->boolean('pdf_show_signature')->default(true);
            $table->boolean('pdf_show_payment_info')->default(true);
            $table->boolean('payment_btc_enabled')->default(false);
            $table->boolean('payment_bank_enabled')->default(true);
            $table->boolean('send_email_after_issue')->default(false);
            $table->string('email_bcc')->nullable();
            $table->json('tags')->nullable();
            $table->foreignUuid('last_generated_document_id')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_issue_date']);
        });

        Schema::create('business_recurring_profile_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('business_recurring_profile_id')->constrained('business_recurring_profiles')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->string('unit', 32)->default('ks');
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_recurring_profile_lines');
        Schema::dropIfExists('business_recurring_profiles');
    }
};
