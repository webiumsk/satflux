<?php

namespace Database\Factories;

use App\Enums\CompanyWarehouseType;
use App\Models\Company;
use App\Models\CompanyWarehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyWarehouse>
 */
class CompanyWarehouseFactory extends Factory
{
    protected $model = CompanyWarehouse::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->city().' sklad',
            'type' => CompanyWarehouseType::Own,
            'deduct_on_issue' => true,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function defaultWarehouse(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
            'name' => 'Hlavný sklad',
        ]);
    }
}
