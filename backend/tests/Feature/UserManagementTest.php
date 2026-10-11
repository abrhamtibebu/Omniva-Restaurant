<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_barman_and_storekeeper(): void
    {
        $this->actingAsRole(RoleSlug::Admin);

        foreach ([RoleSlug::Barman, RoleSlug::Storekeeper] as $slug) {
            $role = Role::query()->where('slug', $slug->value)->firstOrFail();

            $this->postJson('/api/v1/users', [
                'name' => $slug->label(),
                'email' => $slug->value.'@omniva.test',
                'password' => 'Password123!',
                'phone' => '0911000000',
                'role_id' => $role->id,
                'branch_id' => $this->branch->id,
            ])->assertCreated()
                ->assertJsonPath('data.role.slug', $slug->value);
        }
    }

    public function test_manager_cannot_create_an_admin(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $admin = Role::query()->where('slug', RoleSlug::Admin->value)->firstOrFail();

        $this->postJson('/api/v1/users', [
            'name' => 'Extra Admin',
            'email' => 'extra-admin@omniva.test',
            'password' => 'Password123!',
            'role_id' => $admin->id,
            'branch_id' => $this->branch->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }

    public function test_deactivated_user_cannot_call_the_api(): void
    {
        $waiter = $this->user(RoleSlug::Waiter);
        $this->actingAsRole(RoleSlug::Admin);

        $this->deleteJson('/api/v1/users/'.$waiter->id)->assertOk();

        Sanctum::actingAs($waiter->fresh());

        $this->getJson('/api/v1/me')->assertForbidden();
    }
}
