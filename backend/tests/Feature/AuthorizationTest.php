<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_cannot_manage_staff(): void
    {
        $this->actingAsRole(RoleSlug::Waiter);

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', [
            'name' => 'New',
            'email' => 'new@omniva.test',
            'password' => 'Password123!',
            'role_id' => 1,
            'branch_id' => $this->branch->id,
        ])->assertForbidden();
    }

    public function test_kitchen_cannot_process_payment(): void
    {
        $this->actingAsRole(RoleSlug::Kitchen);

        $this->postJson('/api/v1/orders')->assertForbidden();
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_kitchen_can_view_kitchen_tickets(): void
    {
        $this->actingAsRole(RoleSlug::Kitchen);

        $this->getJson('/api/v1/kitchen/orders')->assertOk();
    }
}
