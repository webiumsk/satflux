<?php

use App\Enums\CompanyWarehouseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default(CompanyWarehouseType::Own->value);
            $table->boolean('deduct_on_issue')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('company_contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('company_stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_warehouse_id')->constrained('company_warehouses')->cascadeOnDelete();
            $table->foreignUuid('company_stock_item_id')->constrained('company_stock_items')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_warehouse_id', 'company_stock_item_id']);
            $table->index(['company_stock_item_id']);
        });

        Schema::table('company_stock_item_movements', function (Blueprint $table) {
            $table->foreignUuid('company_warehouse_id')
                ->nullable()
                ->after('company_id')
                ->constrained('company_warehouses')
                ->nullOnDelete();
        });

        Schema::table('business_document_lines', function (Blueprint $table) {
            $table->foreignUuid('company_warehouse_id')
                ->nullable()
                ->after('company_stock_item_id')
                ->constrained('company_warehouses')
                ->nullOnDelete();
        });

        $this->migrateExistingStockData();

        Schema::table('company_stock_items', function (Blueprint $table) {
            $table->dropColumn('quantity_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('company_stock_items', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 14, 4)->default(0)->after('track_inventory');
        });

        $this->restoreQuantityOnHandFromBalances();

        Schema::table('business_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_warehouse_id');
        });

        Schema::table('company_stock_item_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_warehouse_id');
        });

        Schema::dropIfExists('company_stock_balances');
        Schema::dropIfExists('company_warehouses');
    }

    protected function migrateExistingStockData(): void
    {
        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $warehouseId = (string) Str::uuid();
            $now = now();

            DB::table('company_warehouses')->insert([
                'id' => $warehouseId,
                'company_id' => $companyId,
                'name' => 'Hlavný sklad',
                'type' => CompanyWarehouseType::Own->value,
                'deduct_on_issue' => true,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $items = DB::table('company_stock_items')
                ->where('company_id', $companyId)
                ->get(['id', 'quantity_on_hand']);

            foreach ($items as $item) {
                $qty = (float) $item->quantity_on_hand;
                if (abs($qty) < 0.00001) {
                    continue;
                }

                DB::table('company_stock_balances')->insert([
                    'id' => (string) Str::uuid(),
                    'company_warehouse_id' => $warehouseId,
                    'company_stock_item_id' => $item->id,
                    'quantity_on_hand' => $qty,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('company_stock_item_movements')
                ->where('company_id', $companyId)
                ->update(['company_warehouse_id' => $warehouseId]);
        }
    }

    protected function restoreQuantityOnHandFromBalances(): void
    {
        $items = DB::table('company_stock_items')->pluck('id');

        foreach ($items as $itemId) {
            $total = (float) DB::table('company_stock_balances')
                ->where('company_stock_item_id', $itemId)
                ->sum('quantity_on_hand');

            DB::table('company_stock_items')
                ->where('id', $itemId)
                ->update(['quantity_on_hand' => $total]);
        }
    }
};
