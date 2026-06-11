<?php

namespace Database\Factories;

use App\Models\CompanyStockItem;
use App\Services\Invoicing\CompanyStockBalanceService;
use App\Services\Invoicing\CompanyWarehouseService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyStockItem>
 */
class CompanyStockItemFactory extends Factory
{
    protected $model = CompanyStockItem::class;

    protected float $initialQuantity = 0;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'description' => fake()->optional()->sentence(),
            'unit' => 'ks',
            'track_inventory' => true,
            'purchase_unit_price' => fake()->randomFloat(2, 1, 50),
            'purchase_currency' => 'EUR',
            'sale_unit_price' => fake()->randomFloat(2, 2, 80),
            'exclude_from_suggester' => false,
        ];
    }

    public function withQuantity(float $quantity): static
    {
        return $this->state(fn () => [])->afterCreating(function (CompanyStockItem $item) use ($quantity) {
            if (abs($quantity) < 0.00001) {
                return;
            }

            $warehouse = app(CompanyWarehouseService::class)->ensureDefaultForCompany($item->company);
            app(CompanyStockBalanceService::class)->setQuantity($warehouse, $item, $quantity);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CompanyStockItem $item) {
            app(CompanyWarehouseService::class)->ensureDefaultForCompany($item->company);
        });
    }
}
