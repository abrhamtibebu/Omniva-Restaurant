<?php

namespace Database\Factories;

use App\Enums\KitchenStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'item_name_snapshot' => 'Test Item',
            'price_snapshot' => '100.00',
            'quantity' => 1,
            'subtotal' => '100.00',
            'kitchen_status' => KitchenStatus::New,
            'submitted_to_kitchen' => false,
        ];
    }
}
