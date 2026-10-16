<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MenuItem */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'base_price' => $this->base_price,
            'active' => $this->active,
            'available' => $this->available,
            'requires_kitchen' => $this->requires_kitchen,
            'preparation_time_minutes' => $this->preparation_time_minutes,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => $variant->price,
                'active' => $variant->active,
            ])),
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'active' => $modifier->active,
            ])),
        ];
    }
}
