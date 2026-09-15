<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'branch_id' => Branch::factory(),
            'table_id' => null,
            'waiter_id' => User::factory(),
            'customer_id' => null,
            'type' => OrderType::DineIn,
            'status' => OrderStatus::Draft,
            'subtotal' => '0.00',
            'discount' => '0.00',
            'tax' => '0.00',
            'tax_rate' => '15.00',
            'total' => '0.00',
            'opened_at' => now(),
        ];
    }
}
