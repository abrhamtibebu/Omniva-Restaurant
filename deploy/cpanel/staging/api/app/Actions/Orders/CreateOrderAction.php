<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Exceptions\DomainException;
use App\Models\Branch;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\SequenceService;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(private SequenceService $sequences) {}

    /**
     * @param  array{table_id?: int|null, customer_id?: int|null, type?: string, notes?: string|null}  $data
     */
    public function execute(User $waiter, Branch $branch, array $data): Order
    {
        return DB::transaction(function () use ($waiter, $branch, $data) {
            $table = null;

            if (! empty($data['table_id'])) {
                $table = RestaurantTable::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($data['table_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($table->current_order_id) {
                    throw new DomainException('This table already has an open order.');
                }
            }

            $type = OrderType::from($data['type'] ?? OrderType::DineIn->value);

            if ($type === OrderType::DineIn && $table === null) {
                throw new DomainException('A dine-in order needs a table.');
            }

            $order = Order::query()->create([
                'order_number' => $this->sequences->nextOrderNumber($branch->id),
                'branch_id' => $branch->id,
                'table_id' => $table?->id,
                'waiter_id' => $waiter->id,
                'customer_id' => $data['customer_id'] ?? null,
                'type' => $type,
                'status' => OrderStatus::Draft,
                'subtotal' => '0.00',
                'discount' => '0.00',
                'tax' => '0.00',
                'tax_rate' => $branch->tax_rate,
                'total' => '0.00',
                'notes' => $data['notes'] ?? null,
                'opened_at' => now(),
            ]);

            if ($table) {
                $table->update([
                    'current_order_id' => $order->id,
                    'assigned_waiter_id' => $waiter->id,
                    'status' => TableStatus::Occupied,
                ]);
            }

            return $order;
        });
    }
}
