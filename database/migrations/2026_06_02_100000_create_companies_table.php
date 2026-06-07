<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('SK');
            $table->string('state_region')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('default_currency', 3)->default('EUR');
            $table->string('jurisdiction', 32)->default('eu_sk');
            $table->boolean('vat_payer')->default(false);
            $table->decimal('vat_rate_default', 5, 2)->nullable();
            $table->text('legal_footer_note')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_phone')->nullable();
            $table->string('issuer_email')->nullable();
            $table->string('website')->nullable();
            $table->string('invoice_number_prefix', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
