<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\CompanyStockBalance;
use App\Models\CompanyStockItem;
use App\Models\CompanyWarehouse;
use App\Services\Invoicing\CompanyStockBalanceService;
use App\Services\Invoicing\CompanyWarehouseService;

trait CreatesCompanyStock
{
    protected function defaultWarehouse(Company $company): CompanyWarehouse
    {
        return app(CompanyWarehouseService::class)->ensureDefaultForCompany($company);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createStockItem(Company $company, array $attributes = [], float $quantity = 0): CompanyStockItem
    {
        $warehouse = $this->defaultWarehouse($company);
        $item = CompanyStockItem::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Test item',
            'unit' => 'ks',
            'track_inventory' => true,
        ], $attributes));

        if ($quantity !== 0.0) {
            app(CompanyStockBalanceService::class)->setQuantity($warehouse, $item, $quantity);
        }

        return $item->fresh(['balances']);
    }

    protected function stockQuantity(CompanyStockItem $item, ?CompanyWarehouse $warehouse = null): float
    {
        $warehouse ??= $this->defaultWarehouse($item->company);

        return (float) CompanyStockBalance::query()
            ->where('company_warehouse_id', $warehouse->id)
            ->where('company_stock_item_id', $item->id)
            ->value('quantity_on_hand');
    }
}
