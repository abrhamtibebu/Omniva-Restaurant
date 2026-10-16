<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Modifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modifier>
 */
class ModifierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->randomElement(['Extra cheese', 'Extra meat', 'Extra sauce']),
            'price' => '25.00',
            'active' => true,
        ];
    }
}
