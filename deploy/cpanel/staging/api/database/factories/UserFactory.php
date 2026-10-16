<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('09########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::factory(),
            'branch_id' => Branch::factory(),
            'active' => true,
        ];
    }

    public function role(RoleSlug $slug): static
    {
        return $this->state(function () use ($slug) {
            $role = Role::query()->where('slug', $slug->value)->first()
                ?? Role::factory()->slug($slug)->create();

            return ['role_id' => $role->id];
        });
    }
}
