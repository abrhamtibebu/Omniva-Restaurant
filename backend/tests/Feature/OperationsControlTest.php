<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RecipeItem;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_transfer_is_not_a_sale_and_redemption_is_idempotent(): void
    {
        $store = $this->actingAsRole(RoleSlug::Storekeeper);
        $barman = $this->user(RoleSlug::Barman);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '10.000',
            'name' => 'Beer bottle',
        ]);

        Sanctum::actingAs($barman);
        $request = $this->postJson('/api/v1/bono/stock-requests', [
            'destination' => 'bar',
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => '4']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($store);
        $this->postJson('/api/v1/bono/stock-requests/'.$request['id'].'/approve', [
            'lines' => [['id' => $request['lines'][0]['id'], 'approved_quantity' => '4']],
        ])->assertOk();
        $this->postJson('/api/v1/bono/stock-requests/'.$request['id'].'/issue')->assertOk();

        $this->assertSame('6.000', (string) $item->fresh()->quantity_on_hand);
        $this->assertSame(0, Order::query()->count());

        Sanctum::actingAs($barman);
        $this->postJson('/api/v1/bono/stock-requests/'.$request['id'].'/confirm', [
            'lines' => [['id' => $request['lines'][0]['id'], 'received_quantity' => '4']],
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $bar = StockLocation::query()->where('branch_id', $this->branch->id)->where('type', 'bar')->firstOrFail();
        $this->assertSame('4.000', (string) StockBalance::query()
            ->where('inventory_item_id', $item->id)
            ->where('stock_location_id', $bar->id)
            ->first()
            ->quantity);

        $this->actingAsRole(RoleSlug::Admin);
        $menuItem = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id,
            'name' => 'St. George',
            'base_price' => '80.00',
        ]);
        $type = $this->postJson('/api/v1/bono/types', [
            'name' => 'Beer bono',
            'unit_value' => '80',
            'redemption_department' => 'bar',
            'menu_item_ids' => [$menuItem->id],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($barman);
        $payload = [
            'bono_type_id' => $type,
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => '1',
            'idempotency_key' => 'redeem-once',
        ];
        $this->postJson('/api/v1/bono/bar-redemptions', $payload)->assertCreated();
        $this->postJson('/api/v1/bono/bar-redemptions', $payload)->assertCreated();

        $this->assertSame('3.000', (string) StockBalance::query()
            ->where('inventory_item_id', $item->id)
            ->where('stock_location_id', $bar->id)
            ->first()
            ->quantity);
        $this->assertDatabaseCount('bar_redemptions', 1);
    }

    public function test_kitchen_balance_is_consumed_once_by_pos_and_not_by_a_summary(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $beef = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '5.000',
        ]);
        $menu = MenuItem::factory()->create(['base_price' => '100.00']);
        $menu->category->update(['branch_id' => $this->branch->id]);
        RecipeItem::query()->create([
            'menu_item_id' => $menu->id,
            'inventory_item_id' => $beef->id,
            'quantity_required' => '0.150',
            'unit' => $beef->unit,
        ]);

        $this->getJson('/api/v1/bono/stock-locations')->assertOk();
        $kitchen = StockLocation::query()->where('branch_id', $this->branch->id)->where('type', 'kitchen')->firstOrFail();
        StockBalance::query()->create([
            'inventory_item_id' => $beef->id,
            'stock_location_id' => $kitchen->id,
            'quantity' => '2.000',
        ]);

        $orderId = $this->postJson('/api/v1/orders', ['type' => 'takeaway'])->json('data.id');
        $this->postJson("/api/v1/orders/{$orderId}/items", [
            'menu_item_id' => $menu->id,
            'quantity' => 2,
        ]);
        $this->postJson("/api/v1/orders/{$orderId}/submit");
        $order = Order::query()->findOrFail($orderId);
        $this->actingAsRole(RoleSlug::Cashier);
        $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'amount' => (string) $order->total,
            'payment_method' => 'cash',
        ])->assertSuccessful();

        $this->assertSame('5.000', (string) $beef->fresh()->quantity_on_hand);
        $this->assertSame('1.700', (string) StockBalance::query()
            ->where('inventory_item_id', $beef->id)
            ->where('stock_location_id', $kitchen->id)
            ->first()
            ->quantity);

        app(InventoryService::class)->deductForOrder($order->fresh());
        $this->assertSame('1.700', (string) StockBalance::query()
            ->where('inventory_item_id', $beef->id)
            ->where('stock_location_id', $kitchen->id)
            ->first()
            ->quantity);

        $kitchenUser = $this->actingAsRole(RoleSlug::Kitchen);
        $this->postJson('/api/v1/bono/kitchen-sales-summaries', [
            'lines' => [['menu_item_id' => $menu->id, 'quantity' => '2', 'source' => 'pos']],
        ])->assertCreated();

        $this->assertSame(1, Order::query()->where('status', 'completed')->count());
        $this->assertSame('1.700', (string) StockBalance::query()
            ->where('inventory_item_id', $beef->id)
            ->where('stock_location_id', $kitchen->id)
            ->first()
            ->quantity);
        unset($kitchenUser);
    }

    public function test_only_accepted_receipt_increases_stock_after_approval(): void
    {
        $this->actingAsRole(RoleSlug::Storekeeper);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '0.000',
        ]);
        $supplier = Supplier::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Brewery',
            'active' => true,
        ]);

        $purchase = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'approval_status' => 'pending',
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity' => '10',
                'unit_cost' => '20',
            ]],
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/purchases/'.$purchase['id'].'/receive')->assertStatus(422);

        $this->actingAsRole(RoleSlug::Admin);
        $this->postJson('/api/v1/purchases/'.$purchase['id'].'/decide', [
            'decision' => 'approved',
        ])->assertOk();

        $this->actingAsRole(RoleSlug::Storekeeper);
        $this->postJson('/api/v1/purchases/'.$purchase['id'].'/receive', [
            'items' => [[
                'id' => $purchase['items'][0]['id'],
                'accepted_quantity' => '8',
            ]],
        ])->assertOk();

        $this->assertSame('8.000', (string) $item->fresh()->quantity_on_hand);
    }

    public function test_physical_count_does_not_overwrite_the_ledger(): void
    {
        $barman = $this->actingAsRole(RoleSlug::Barman);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '0.000',
        ]);
        $this->getJson('/api/v1/bono/stock-locations')->assertOk();
        $bar = StockLocation::query()->where('type', 'bar')->where('branch_id', $this->branch->id)->firstOrFail();
        StockBalance::query()->create([
            'inventory_item_id' => $item->id,
            'stock_location_id' => $bar->id,
            'quantity' => '10.000',
        ]);

        $this->postJson('/api/v1/bono/stock-counts', [
            'location' => 'bar',
            'lines' => [['inventory_item_id' => $item->id, 'physical_quantity' => '9']],
        ])->assertCreated();

        $this->assertSame('10.000', (string) StockBalance::query()
            ->where('inventory_item_id', $item->id)
            ->where('stock_location_id', $bar->id)
            ->first()
            ->quantity);
        $this->assertDatabaseHas('discrepancy_cases', ['type' => 'stock_count', 'status' => 'open']);
        unset($barman);
    }
}
