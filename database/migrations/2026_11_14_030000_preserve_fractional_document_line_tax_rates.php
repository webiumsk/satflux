<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_document_lines', function (Blueprint $table) {
            $table->decimal('tax_rate', 7, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        $connection = DB::connection();
        $connection->transaction(function () use ($connection): void {
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement('LOCK TABLE business_document_lines IN ACCESS EXCLUSIVE MODE');
            }
            if ($connection->table('business_document_lines')->whereRaw('tax_rate <> ROUND(tax_rate, 2)')->exists()) {
                throw new RuntimeException('Cannot narrow document tax rates while fractional percentages require more than two decimal places.');
            }

            $connection->getSchemaBuilder()->table('business_document_lines', function (Blueprint $table) {
                $table->decimal('tax_rate', 5, 2)->default(0)->change();
            });
        });
    }
};
