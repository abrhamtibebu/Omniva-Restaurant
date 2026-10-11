<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Enums\StockLocationType;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\StockBalance;
use App\Services\StockControlService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuBonoTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_create_bono_type_linked_to_many_menu_items(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);
        $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
        $items = collect(['St. George', 'Habesha', 'Castle'])->map(
            fn (string $name) => MenuItem::factory()->create([
                'category_id' => $category->id,
                'name' => $name,
                'base_price' => '80.00',
            ])
        );

        $response = $this->postJson('/api/v1/bono/types', [
            'name' => 'Beer',
            'unit_value' => '80',
            'redemption_department' => 'bar',
            'menu_item_ids' => $items->pluck('id')->all(),
        ])->assertCreated();

        $response->assertJsonPath('data.name', 'Beer')
            ->assertJsonCount(3, 'data.menu_items');

        $listed = $this->getJson('/api/v1/bono/types')->assertOk()->json('data');
        $this->assertCount(1, $listed);
        $this->assertEqualsCanonicalizing(
            $items->pluck('id')->all(),
            collect($listed[0]['menu_items'])->pluck('id')->all()
        );
    }

    public function test_cannot_link_menu_item_from_another_branch(): void
    {
        $this->actingAsRole(RoleSlug::Admin);
        $local = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id,
            'name' => 'Local Beer',
        ]);

        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = Branch::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        app(SubscriptionService::class)->grantComplimentary($otherRestaurant);
        $foreign = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory()->create(['branch_id' => $otherBranch->id])->id,
            'name' => 'Foreign Beer',
        ]);

        $this->postJson('/api/v1/bono/types', [
            'name' => 'Beer',
            'unit_value' => '80',
            'redemption_department' => 'bar',
            'menu_item_ids' => [$local->id, $foreign->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_ids']);
    }

    public function test_bar_redemption_requires_a_linked_menu_item(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);
        $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
        $stGeorge = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'St. George',
            'base_price' => '80.00',
        ]);
        $habesha = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Habesha',
            'base_price' => '80.00',
        ]);

        $typeId = $this->postJson('/api/v1/bono/types', [
            'name' => 'Beer',
            'unit_value' => '80',
            'redemption_department' => 'bar',
            'menu_item_ids' => [$stGeorge->id, $habesha->id],
        ])->assertCreated()->json('data.id');

        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '10.000',
            'name' => 'Beer bottle',
        ]);
        $bar = app(StockControlService::class)->location($this->branch->id, StockLocationType::Bar);
        StockBalance::query()->create([
            'inventory_item_id' => $item->id,
            'stock_location_id' => $bar->id,
            'quantity' => '5.000',
        ]);

        Sanctum::actingAs($this->user(RoleSlug::Barman));

        $this->postJson('/api/v1/bono/bar-redemptions', [
            'bono_type_id' => $typeId,
            'inventory_item_id' => $item->id,
            'quantity' => '1',
            'idempotency_key' => 'need-menu',
        ])->assertStatus(422);

        $unlinked = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Wine',
        ]);

        $this->postJson('/api/v1/bono/bar-redemptions', [
            'bono_type_id' => $typeId,
            'menu_item_id' => $unlinked->id,
            'inventory_item_id' => $item->id,
            'quantity' => '1',
            'idempotency_key' => 'wrong-menu',
        ])->assertStatus(422);

        $this->postJson('/api/v1/bono/bar-redemptions', [
            'bono_type_id' => $typeId,
            'menu_item_id' => $stGeorge->id,
            'inventory_item_id' => $item->id,
            'quantity' => '1',
            'idempotency_key' => 'ok-menu',
        ])->assertCreated();

        $this->assertSame('4.000', (string) StockBalance::query()
            ->where('inventory_item_id', $item->id)
            ->where('stock_location_id', $bar->id)
            ->value('quantity'));
    }
}
