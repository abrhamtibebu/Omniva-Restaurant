<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RestaurantTable */
class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'active' => $this->active,
            'assigned_waiter_id' => $this->assigned_waiter_id,
            'current_order_id' => $this->current_order_id,
            'waiter' => $this->whenLoaded('assignedWaiter', fn () => $this->assignedWaiter ? [
                'id' => $this->assignedWaiter->id,
                'name' => $this->assignedWaiter->name,
            ] : null),
            'current_order' => $this->whenLoaded('currentOrder', fn () => $this->currentOrder ? [
                'id' => $this->currentOrder->id,
                'order_number' => $this->currentOrder->order_number,
                'status' => $this->currentOrder->status->value,
                'total' => $this->currentOrder->total,
            ] : null),
        ];
    }
}
