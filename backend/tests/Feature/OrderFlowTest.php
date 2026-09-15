<?php

namespace Tests\Feature;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleSlug;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_add_item_and_backend_totals(): void
    {
        $waiter = $this->actingAsRole(RoleSlug::Waiter);
        $table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => '8']);
        $item = MenuItem::factory()->create([
            'base_price' => '200.00',
        ]);
        $item->category->update(['branch_id' => $this->branch->id]);

        $create = $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'type' => 'dine_in',
        ])->assertSuccessful()
            ->assertJsonPath('data.status', 'draft');

        $orderId = $create->json('data.id');

        $this->postJson("/api/v1/orders/{$orderId}/items", [
            'menu_item_id' => $item->id,
            'quantity' => 2,
        ])->assertSuccessful();

        $order = Order::query()->findOrFail($orderId);
        $expectedSubtotal = '400.00';
        $expectedTax = Money::percent($expectedSubtotal, (string) $this->branch->tax_rate);

        $this->assertSame($expectedSubtotal, (string) $order->subtotal);
        $this->assertSame($expectedTax, (string) $order->tax);
        $this->assertSame(Money::add($expectedSubtotal, $expectedTax), (string) $order->total);
        $this->assertSame($waiter->id, $order->waiter_id);
    }

    public function test_submit_and_cancel_order(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $item = MenuItem::factory()->create(['base_price' => '100.00']);
        $item->category->update(['branch_id' => $this->branch->id]);

        $orderId = $this->postJson('/api/v1/orders', ['type' => 'takeaway'])->json('data.id');

        $this->postJson("/api/v1/orders/{$orderId}/items", [
            'menu_item_id' => $item->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/v1/orders/{$orderId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Submitted->value);

        $this->postJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
    }

    public function test_kitchen_status_transitions_are_validated(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $item = MenuItem::factory()->create(['base_price' => '100.00']);
        $item->category->update(['branch_id' => $this->branch->id]);

        $orderId = $this->postJson('/api/v1/orders', ['type' => 'takeaway'])->json('data.id');
        $lineId = $this->postJson("/api/v1/orders/{$orderId}/items", [
            'menu_item_id' => $item->id,
            'quantity' => 1,
        ])->json('data.id');

        $this->actingAsRole(RoleSlug::Kitchen);

        $this->patchJson("/api/v1/order-items/{$lineId}/kitchen-status", [
            'status' => KitchenStatus::Preparing->value,
        ])->assertUnprocessable();

        $this->actingAsRole(RoleSlug::Manager);
        $this->postJson("/api/v1/orders/{$orderId}/submit")->assertOk();

        $this->actingAsRole(RoleSlug::Kitchen);
        $this->patchJson("/api/v1/order-items/{$lineId}/kitchen-status", [
            'status' => KitchenStatus::Ready->value,
        ])->assertUnprocessable();

        $this->patchJson("/api/v1/order-items/{$lineId}/kitchen-status", [
            'status' => KitchenStatus::Preparing->value,
        ])->assertOk();

        $this->patchJson("/api/v1/order-items/{$lineId}/kitchen-status", [
            'status' => KitchenStatus::Ready->value,
        ])->assertOk();

        $this->assertSame(OrderStatus::Ready, Order::query()->find($orderId)->status);
    }
}
