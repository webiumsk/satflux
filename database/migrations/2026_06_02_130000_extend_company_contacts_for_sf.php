<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->string('registration_number', 32)->nullable()->after('name');
            $table->string('vat_id', 32)->nullable()->after('tax_id');
            $table->string('fax', 64)->nullable()->after('phone');
            $table->string('bank_account', 64)->nullable()->after('country');
            $table->string('bank_code', 16)->nullable()->after('bank_account');
            $table->string('iban', 64)->nullable()->after('bank_code');
            $table->string('swift', 16)->nullable()->after('iban');
            $table->string('delivery_street')->nullable()->after('swift');
            $table->string('delivery_postal_code', 32)->nullable()->after('delivery_street');
            $table->string('delivery_city', 128)->nullable()->after('delivery_postal_code');
            $table->string('delivery_country', 64)->nullable()->after('delivery_city');
            $table->json('contact_persons')->nullable()->after('notes');
        });

        Schema::table('company_contacts', function (Blueprint $table) {
            $table->string('country', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->change();
        });

        Schema::table('company_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'registration_number',
                'vat_id',
                'fax',
                'bank_account',
                'bank_code',
                'iban',
                'swift',
                'delivery_street',
                'delivery_postal_code',
                'delivery_city',
                'delivery_country',
                'contact_persons',
            ]);
        });
    }
};
