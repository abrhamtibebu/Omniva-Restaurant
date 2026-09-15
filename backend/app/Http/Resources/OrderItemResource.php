<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'menu_item_id' => $this->menu_item_id,
            'menu_item_variant_id' => $this->menu_item_variant_id,
            'item_name_snapshot' => $this->item_name_snapshot,
            'price_snapshot' => $this->price_snapshot,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
            'notes' => $this->notes,
            'kitchen_status' => $this->kitchen_status->value,
            'submitted_to_kitchen' => $this->submitted_to_kitchen,
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($modifier) => [
                'id' => $modifier->id,
                'modifier_id' => $modifier->modifier_id,
                'name' => $modifier->modifier_name_snapshot,
                'price' => $modifier->price_snapshot,
            ])),
        ];
    }
}
