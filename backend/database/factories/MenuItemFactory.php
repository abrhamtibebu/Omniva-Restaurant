<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => MenuCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'base_price' => fake()->randomElement(['80.00', '120.00', '180.00', '250.00', '350.00']),
            'active' => true,
            'available' => true,
            'requires_kitchen' => true,
            'preparation_time_minutes' => 15,
        ];
    }
}
