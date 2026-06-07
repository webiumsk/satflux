<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->string('name')->nullable()->after('document_type');
            $table->string('format', 32)->default('RRRRCCCC')->after('name');
            $table->string('reset_period', 16)->default('yearly')->after('format');
            $table->boolean('is_default')->default(false)->after('reset_period');
            $table->string('period_key', 16)->nullable()->after('year');
        });

        foreach (DB::table('company_document_sequences')->get() as $row) {
            DB::table('company_document_sequences')->where('id', $row->id)->update([
                'name' => $this->defaultNameForType($row->document_type),
                'format' => 'RRRRCCCC',
                'reset_period' => 'yearly',
                'is_default' => true,
                'period_key' => (string) $row->year,
            ]);
        }

        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'document_type', 'year']);
            $table->dropColumn('year');
        });
    }

    public function down(): void
    {
        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('document_type');
        });

        foreach (DB::table('company_document_sequences')->get() as $row) {
            $year = is_numeric($row->period_key) ? (int) $row->period_key : (int) date('Y');
            DB::table('company_document_sequences')->where('id', $row->id)->update(['year' => $year]);
        }

        Schema::table('company_document_sequences', function (Blueprint $table) {
            $table->unique(['company_id', 'document_type', 'year']);
            $table->dropColumn(['name', 'format', 'reset_period', 'is_default', 'period_key']);
        });
    }

    protected function defaultNameForType(string $type): string
    {
        return match ($type) {
            'invoice' => 'Faktúra',
            'credit_note' => 'Dobropis',
            'proforma' => 'Zálohová faktúra',
            'delivery_note' => 'Dodací list',
            'quote' => 'Cenová ponuka',
            'order_received' => 'Prijatá objednávka',
            'order_issued' => 'Vydaná objednávka',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
};
