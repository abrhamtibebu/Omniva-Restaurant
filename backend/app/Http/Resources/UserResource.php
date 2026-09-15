<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => $this->active,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'slug' => $this->role->slug,
                'name' => $this->role->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'restaurant_id' => $this->branch->restaurant_id,
                'name' => $this->branch->name,
                'phone' => $this->branch->phone,
                'address' => $this->branch->address,
                'currency' => $this->branch->currency,
                'timezone' => $this->branch->timezone,
                'tax_rate' => $this->branch->tax_rate,
                'tax_identification_number' => $this->branch->tax_identification_number,
                'active' => $this->branch->active,
                'restaurant' => $this->branch->relationLoaded('restaurant') && $this->branch->restaurant
                    ? [
                        'id' => $this->branch->restaurant->id,
                        'name' => $this->branch->restaurant->name,
                        'phone' => $this->branch->restaurant->phone,
                        'address' => $this->branch->restaurant->address,
                        'tax_identification_number' => $this->branch->restaurant->tax_identification_number,
                        'currency' => $this->branch->restaurant->currency,
                        'timezone' => $this->branch->restaurant->timezone,
                        'active' => $this->branch->restaurant->active,
                    ]
                    : null,
            ] : null),
            'permissions' => $this->permissionValues(),
            'available_branches' => $this->when(
                $request->user() === null || $request->user()->is($this->resource),
                fn () => $this->viewableBranches()->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'address' => $branch->address,
                    'phone' => $branch->phone,
                    'active' => $branch->active,
                ])->values(),
            ),
            'can_switch_branch' => $this->when(
                $request->user() === null || $request->user()->is($this->resource),
                fn () => $this->canManageBranches(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
