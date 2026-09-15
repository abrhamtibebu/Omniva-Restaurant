<?php

namespace Database\Factories;

use App\Enums\InventoryUnit;
use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->unique()->word(),
            'sku' => fake()->unique()->bothify('SKU-###'),
            'unit' => fake()->randomElement(InventoryUnit::cases()),
            'quantity_on_hand' => '20.000',
            'minimum_stock' => '5.000',
            'average_cost' => '10.00',
            'active' => true,
        ];
    }
}
