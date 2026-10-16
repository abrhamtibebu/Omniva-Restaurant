<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hideMoney = $request->user()?->hasPermission(Permission::KitchenView)
            && ! $request->user()?->hasPermission(Permission::OrdersView)
            && ! $request->user()?->hasPermission(Permission::OrdersViewOwn)
            && ! $request->user()?->hasPermission(Permission::PaymentsView);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'branch_id' => $this->branch_id,
            'table_id' => $this->table_id,
            'waiter_id' => $this->waiter_id,
            'customer_id' => $this->customer_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'table' => $this->whenLoaded('table', fn () => $this->table ? [
                'id' => $this->table->id,
                'name' => $this->table->name,
            ] : null),
            'waiter' => $this->whenLoaded('waiter', fn () => [
                'id' => $this->waiter->id,
                'name' => $this->waiter->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->when(! $hideMoney, PaymentResource::collection($this->whenLoaded('payments'))),
            $this->mergeWhen(! $hideMoney, [
                'subtotal' => $this->subtotal,
                'discount' => $this->discount,
                'tax' => $this->tax,
                'tax_rate' => $this->tax_rate,
                'total' => $this->total,
                'amount_paid' => $this->whenLoaded('payments', fn () => $this->amountPaid()),
                'balance_due' => $this->whenLoaded('payments', fn () => $this->balanceDue()),
            ]),
        ];
    }
}
