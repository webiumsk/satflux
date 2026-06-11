<?php

namespace Database\Factories;

use App\Models\CompanyStockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyStockItem>
 */
class CompanyStockItemFactory extends Factory
{
    protected $model = CompanyStockItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'description' => fake()->optional()->sentence(),
            'unit' => 'ks',
            'track_inventory' => true,
            'quantity_on_hand' => fake()->randomFloat(2, 0, 100),
            'purchase_unit_price' => fake()->randomFloat(2, 1, 50),
            'purchase_currency' => 'EUR',
            'sale_unit_price' => fake()->randomFloat(2, 2, 80),
            'exclude_from_suggester' => false,
        ];
    }
}
