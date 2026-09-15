<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RecipeItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_increases_stock(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '10.000',
        ]);
        $supplier = Supplier::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Supplier',
            'active' => true,
        ]);

        $purchaseId = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity' => '5',
                'unit_cost' => '12.00',
            ]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/purchases/{$purchaseId}/receive")->assertOk();

        $this->assertSame('15.000', (string) $item->fresh()->quantity_on_hand);
    }

    public function test_completed_order_deducts_ingredients_once(): void
    {
        $this->actingAsRole(RoleSlug::Manager);

        $beef = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => '5.000',
            'sku' => 'BEEF-1',
        ]);
        $menu = MenuItem::factory()->create(['base_price' => '100.00']);
        $menu->category->update(['branch_id' => $this->branch->id]);

        RecipeItem::query()->create([
            'menu_item_id' => $menu->id,
            'inventory_item_id' => $beef->id,
            'quantity_required' => '0.150',
            'unit' => $beef->unit,
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

        $this->assertSame('4.700', (string) $beef->fresh()->quantity_on_hand);
        $this->assertNotNull($order->fresh()->inventory_deducted_at);

        app(InventoryService::class)->deductForOrder($order->fresh());
        $this->assertSame('4.700', (string) $beef->fresh()->quantity_on_hand);
    }
}
