<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BonoSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_cannot_see_another_restaurants_bonos_or_users(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $menuItem = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id,
            'name' => 'House Lager',
            'base_price' => '50.00',
        ]);

        $this->postJson('/api/v1/bono/types', [
            'name' => 'House Beer',
            'unit_value' => '50',
            'redemption_department' => 'bar',
            'menu_item_ids' => [$menuItem->id],
        ])->assertCreated();

        $this->postJson('/api/v1/register', [
            'restaurant_name' => 'Second House',
            'owner_name' => 'Second Owner',
            'email' => 'owner@second.test',
            'password' => 'Password123!',
        ])->assertCreated()
            ->assertJsonPath('user.bono_control.status', 'trialing')
            ->assertJsonPath('user.bono_control.entitled', true);

        $owner = User::query()->where('email', 'owner@second.test')->firstOrFail();
        Sanctum::actingAs($owner);

        $types = $this->getJson('/api/v1/bono/types')->assertOk()->json('data');
        $this->assertNotContains('House Beer', array_column($types, 'name'));

        $emails = array_column($this->getJson('/api/v1/users')->assertOk()->json('data'), 'email');
        $this->assertNotContains($admin->email, $emails);
        $this->assertContains('owner@second.test', $emails);
    }

    public function test_expired_subscription_locks_bono_and_leaves_pos_open(): void
    {
        $this->actingAsRole(RoleSlug::Admin);

        Subscription::query()->where('restaurant_id', $this->restaurant->id)->update([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/bono/types')->assertStatus(402);
        $this->getJson('/api/v1/bono/stock-requests')->assertStatus(402);
        $this->getJson('/api/v1/orders')->assertOk();
    }

    public function test_platform_payment_activates_the_period(): void
    {
        $operator = $this->actingAsRole(RoleSlug::Admin);
        $operator->update(['is_platform' => true]);

        $this->postJson('/api/v1/register', [
            'restaurant_name' => 'Trial House',
            'owner_name' => 'Trial Owner',
            'email' => 'owner@trial.test',
            'password' => 'Password123!',
        ])->assertCreated();

        $invoice = Invoice::query()->where('status', 'open')->firstOrFail();
        $owner = User::query()->where('email', 'owner@trial.test')->firstOrFail();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/billing/invoices/{$invoice->id}/pay")->assertForbidden();

        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/billing/invoices/{$invoice->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.entitled', true);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/billing')->assertOk()
            ->assertJsonPath('data.subscription.status', 'active');
        $this->getJson('/api/v1/bono/types')->assertOk();
    }

    public function test_directories_keep_bono_roles_off_the_staff_list(): void
    {
        $this->actingAsRole(RoleSlug::Admin);
        $barman = Role::query()->where('slug', RoleSlug::Barman->value)->firstOrFail();

        $this->postJson('/api/v1/users', [
            'directory' => 'staff',
            'name' => 'Wrong List',
            'email' => 'wrong@omniva.test',
            'password' => 'Password123!',
            'role_id' => $barman->id,
            'branch_id' => $this->branch->id,
        ])->assertStatus(422);

        $this->postJson('/api/v1/users', [
            'directory' => 'bono',
            'name' => 'Abel Barman',
            'email' => 'abel@omniva.test',
            'password' => 'Password123!',
            'role_id' => $barman->id,
            'branch_id' => $this->branch->id,
        ])->assertCreated();

        $staffEmails = array_column($this->getJson('/api/v1/users?directory=staff')->json('data'), 'email');
        $bonoEmails = array_column($this->getJson('/api/v1/users?directory=bono')->json('data'), 'email');

        $this->assertNotContains('abel@omniva.test', $staffEmails);
        $this->assertContains('abel@omniva.test', $bonoEmails);
    }
}
