<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->randomElement(RoleSlug::values());

        return [
            'name' => RoleSlug::from($slug)->label(),
            'slug' => $slug,
        ];
    }

    public function slug(RoleSlug $slug): static
    {
        return $this->state(fn () => [
            'name' => $slug->label(),
            'slug' => $slug->value,
        ]);
    }
}
