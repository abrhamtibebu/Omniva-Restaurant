<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = $this->user(RoleSlug::Admin, [
            'email' => 'admin@omniva.test',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@omniva.test',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@omniva.test')
            ->assertJsonStructure(['token', 'user' => ['permissions']]);

        $token = $response->json('token');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user(RoleSlug::Waiter, [
            'email' => 'off@omniva.test',
            'password' => 'Password123!',
            'active' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'off@omniva.test',
            'password' => 'Password123!',
        ])->assertUnprocessable();
    }

    public function test_user_can_login_with_phone(): void
    {
        $this->user(RoleSlug::Cashier, [
            'email' => 'phone.login@omniva.test',
            'phone' => '0911223344',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/v1/login', [
            'login' => '911223344',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'phone.login@omniva.test')
            ->assertJsonPath('user.phone', '251911223344');
    }

    public function test_user_can_logout(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);

        $this->postJson('/api/v1/logout')->assertOk();
    }
}
