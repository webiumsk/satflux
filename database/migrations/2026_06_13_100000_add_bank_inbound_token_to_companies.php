<?php

use App\Models\Company;
use App\Services\Invoicing\BankInboundAddressService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('bank_inbound_token', 16)->nullable()->unique()->after('invoice_number_prefix');
        });

        $service = app(BankInboundAddressService::class);

        Company::query()
            ->whereNull('bank_inbound_token')
            ->orderBy('id')
            ->each(function (Company $company) use ($service): void {
                $company->update([
                    'bank_inbound_token' => $service->generateUniqueToken(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['bank_inbound_token']);
            $table->dropColumn('bank_inbound_token');
        });
    }
};
