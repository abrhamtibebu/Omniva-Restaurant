<?php

namespace Tests;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected Restaurant $restaurant;

    protected Branch $branch;

    protected function seedRolesAndBranch(): void
    {
        foreach (RoleSlug::cases() as $slug) {
            Role::query()->updateOrCreate(
                ['slug' => $slug->value],
                ['name' => $slug->label()],
            );
        }

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = Branch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    protected function user(RoleSlug $slug, array $overrides = []): User
    {
        if (! isset($this->branch)) {
            $this->seedRolesAndBranch();
        }

        return User::factory()
            ->role($slug)
            ->create(array_merge([
                'branch_id' => $this->branch->id,
            ], $overrides));
    }

    protected function actingAsRole(RoleSlug $slug, array $overrides = []): User
    {
        $user = $this->user($slug, $overrides);
        Sanctum::actingAs($user);

        return $user;
    }
}
