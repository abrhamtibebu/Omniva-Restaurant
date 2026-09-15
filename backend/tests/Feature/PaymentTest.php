<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RoleSlug;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_split_payment_then_completion(): void
    {
        $this->actingAsRole(RoleSlug::Manager);
        $item = MenuItem::factory()->create(['base_price' => '200.00']);
        $item->category->update(['branch_id' => $this->branch->id]);

        $orderId = $this->postJson('/api/v1/orders', ['type' => 'takeaway'])->json('data.id');
        $this->postJson("/api/v1/orders/{$orderId}/items", [
            'menu_item_id' => $item->id,
            'quantity' => 1,
        ]);
        $this->postJson("/api/v1/orders/{$orderId}/submit");

        $order = Order::query()->findOrFail($orderId);
        $total = (string) $order->total;
        $half = bcdiv($total, '2', 2);

        $this->actingAsRole(RoleSlug::Cashier);

        $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'amount' => $half,
            'payment_method' => 'cash',
        ])->assertSuccessful();

        $order->refresh()->load('payments');
        $this->assertSame($half, $order->amountPaid());
        $this->assertNotSame(OrderStatus::Completed, $order->status);

        $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'amount' => '9999.00',
            'payment_method' => 'card',
        ])->assertUnprocessable();

        $balance = $order->balanceDue();

        $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'amount' => $balance,
            'payment_method' => 'card',
        ])->assertSuccessful();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }
}
