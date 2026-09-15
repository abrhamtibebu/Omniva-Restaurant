<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderCalculator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class AddOrderItemAction
{
    public function __construct(private OrderCalculator $calculator) {}

    /**
     * @param  array{menu_item_id: int, menu_item_variant_id?: int|null, quantity: int, notes?: string|null, modifier_ids?: list<int>}  $data
     */
    public function execute(Order $order, array $data): OrderItem
    {
        if (! $order->status->isOpen() || $order->status === OrderStatus::Completed) {
            throw new DomainException('This order can no longer be changed.');
        }

        return DB::transaction(function () use ($order, $data) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $menuItem = MenuItem::query()
                ->with(['variants', 'modifiers', 'category'])
                ->findOrFail($data['menu_item_id']);

            if (! $menuItem->active || ! $menuItem->available) {
                throw new DomainException('That menu item is not available.');
            }

            if ($menuItem->category->branch_id !== $order->branch_id) {
                throw new DomainException('That menu item does not belong to this branch.');
            }

            $variant = null;
            $price = (string) $menuItem->base_price;
            $name = $menuItem->name;

            if (! empty($data['menu_item_variant_id'])) {
                $variant = $menuItem->variants->firstWhere('id', (int) $data['menu_item_variant_id']);

                if (! $variant || ! $variant->active) {
                    throw new DomainException('That variant is not available.');
                }

                $price = (string) $variant->price;
                $name .= " ({$variant->name})";
            }

            $quantity = (int) $data['quantity'];

            $item = $order->items()->create([
                'menu_item_id' => $menuItem->id,
                'menu_item_variant_id' => $variant?->id,
                'item_name_snapshot' => $name,
                'price_snapshot' => $price,
                'quantity' => $quantity,
                'subtotal' => Money::multiply($price, (string) $quantity),
                'notes' => $data['notes'] ?? null,
                'kitchen_status' => KitchenStatus::New,
                'submitted_to_kitchen' => false,
            ]);

            $modifierIds = $data['modifier_ids'] ?? [];

            if ($modifierIds !== []) {
                $allowed = $menuItem->modifiers->keyBy('id');

                foreach ($modifierIds as $modifierId) {
                    $modifier = $allowed->get($modifierId);

                    if (! $modifier || ! $modifier->active) {
                        throw new DomainException('An add-on is not valid for this item.');
                    }

                    $item->modifiers()->create([
                        'modifier_id' => $modifier->id,
                        'modifier_name_snapshot' => $modifier->name,
                        'price_snapshot' => $modifier->price,
                    ]);
                }
            }

            $this->calculator->recalculate($order);

            return $item->fresh(['modifiers']);
        });
    }
}
