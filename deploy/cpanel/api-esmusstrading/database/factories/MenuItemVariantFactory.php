<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariant>
 */
class MenuItemVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'price' => '200.00',
            'active' => true,
        ];
    }
}
