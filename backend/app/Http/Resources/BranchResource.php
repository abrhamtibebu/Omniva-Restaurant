<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'tax_rate' => $this->tax_rate,
            'tax_identification_number' => $this->tax_identification_number,
            'active' => $this->active,
            'restaurant' => $this->whenLoaded('restaurant', fn () => $this->restaurant ? [
                'id' => $this->restaurant->id,
                'name' => $this->restaurant->name,
                'phone' => $this->restaurant->phone,
                'address' => $this->restaurant->address,
                'tax_identification_number' => $this->restaurant->tax_identification_number,
                'currency' => $this->restaurant->currency,
                'timezone' => $this->restaurant->timezone,
                'active' => $this->restaurant->active,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
