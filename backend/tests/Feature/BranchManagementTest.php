<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_second_branch_and_switch_to_it(): void
    {
        $this->actingAsRole(RoleSlug::Admin);

        $create = $this->postJson('/api/v1/branches', [
            'name' => 'Piassa',
            'address' => 'Piassa, Addis Ababa',
            'phone' => '+251911000002',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Piassa');

        $newId = $create->json('data.id');

        $this->postJson('/api/v1/me/branch', ['branch_id' => $newId])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $newId)
            ->assertJsonPath('data.branch.name', 'Piassa');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.branch.id', $newId)
            ->assertJsonPath('data.can_switch_branch', true);

        $this->assertCount(2, $this->getJson('/api/v1/me')->json('data.available_branches'));
    }

    public function test_admin_header_overrides_working_branch_without_mutating_assignment(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $other = Branch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mexico',
        ]);

        RestaurantTable::factory()->create([
            'branch_id' => $other->id,
            'name' => 'M1',
        ]);

        $this->getJson('/api/v1/tables')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/tables', ['X-Branch-Id' => (string) $other->id])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'M1');

        $this->assertSame($this->branch->id, $admin->fresh()->branch_id);
    }

    public function test_cloned_menu_appears_on_the_new_branch_only(): void
    {
        $this->actingAsRole(RoleSlug::Admin);

        $category = MenuCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tibs',
        ]);
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Shekla Tibs',
            'base_price' => '450.00',
        ]);

        $newId = $this->postJson('/api/v1/branches', [
            'name' => 'Sarbet',
            'clone_from_branch_id' => $this->branch->id,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/me/branch', ['branch_id' => $newId])->assertOk();

        $this->getJson('/api/v1/menu/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tibs');

        $this->assertDatabaseHas('menu_categories', [
            'branch_id' => $newId,
            'name' => 'Tibs',
        ]);
        $this->assertDatabaseHas('menu_items', [
            'name' => 'Shekla Tibs',
        ]);
        $this->assertSame(2, MenuItem::query()->where('name', 'Shekla Tibs')->count());
    }

    public function test_waiter_cannot_create_or_switch_branches(): void
    {
        $this->actingAsRole(RoleSlug::Waiter);

        $this->postJson('/api/v1/branches', ['name' => 'Secret'])->assertForbidden();
        $this->postJson('/api/v1/me/branch', ['branch_id' => $this->branch->id])->assertForbidden();
        $this->getJson('/api/v1/branches')->assertForbidden();
    }

    public function test_admin_cannot_switch_to_another_restaurants_branch(): void
    {
        $this->actingAsRole(RoleSlug::Admin);

        $foreign = Branch::factory()->create([
            'restaurant_id' => Restaurant::factory()->create()->id,
            'name' => 'Elsewhere',
        ]);

        $this->postJson('/api/v1/me/branch', ['branch_id' => $foreign->id])->assertForbidden();

        $this->getJson('/api/v1/tables', ['X-Branch-Id' => (string) $foreign->id])->assertForbidden();
    }

    public function test_manager_stays_on_assigned_branch(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $other = Branch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'CMC',
        ]);

        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/me/branch', ['branch_id' => $other->id])->assertForbidden();
        $this->postJson('/api/v1/branches', ['name' => 'New'])->assertForbidden();
    }
}
