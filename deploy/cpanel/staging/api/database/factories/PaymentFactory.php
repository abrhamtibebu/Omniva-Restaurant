<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'cashier_id' => User::factory(),
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ];
    }
}
