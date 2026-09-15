<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Events\InventoryLow;
use App\Exceptions\DomainException;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\QueryException;

class InventoryService
{
    /**
     * Record a stock movement and update quantity_on_hand. Always call inside a
     * transaction that also locks the inventory row.
     */
    public function move(
        InventoryItem $item,
        InventoryTransactionType $type,
        string $quantity,
        ?User $user = null,
        ?string $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): InventoryTransaction {
        $item = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

        $signed = $type->isInbound()
            ? $quantity
            : bcmul($quantity, '-1', 3);

        $newQuantity = bcadd((string) $item->quantity_on_hand, $signed, 3);

        if (bccomp($newQuantity, '0', 3) < 0 && ! $type->isInbound()) {
            // Still allow the sale to complete — restaurants run with imperfect
            // stock counts — but never store a silent overwrite.
        }

        $item->quantity_on_hand = $newQuantity;
        $item->save();

        $transaction = InventoryTransaction::query()->create([
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $user?->id,
        ]);

        $item->refresh();

        if ($item->isLowStock()) {
            InventoryLow::dispatch($item);
        }

        return $transaction;
    }

    /**
     * Deduct recipe ingredients once per completed order. The conditional
     * update on inventory_deducted_at is the application-level lock; the unique
     * index on inventory_transactions is the database-level backstop.
     */
    public function deductForOrder(Order $order, ?User $user = null): void
    {
        $claimed = Order::query()
            ->whereKey($order->id)
            ->whereNull('inventory_deducted_at')
            ->update(['inventory_deducted_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $order->load(['items.menuItem.recipeItems.inventoryItem']);

        $usage = [];

        foreach ($order->items as $item) {
            foreach ($item->menuItem?->recipeItems ?? [] as $recipe) {
                $key = $recipe->inventory_item_id;
                $line = bcmul((string) $recipe->quantity_required, (string) $item->quantity, 3);
                $usage[$key] = bcadd($usage[$key] ?? '0', $line, 3);
            }
        }

        try {
            foreach ($usage as $inventoryItemId => $quantity) {
                $inventoryItem = InventoryItem::query()->find($inventoryItemId);

                if (! $inventoryItem) {
                    continue;
                }

                $this->move(
                    item: $inventoryItem,
                    type: InventoryTransactionType::Usage,
                    quantity: $quantity,
                    user: $user,
                    referenceType: Order::class,
                    referenceId: $order->id,
                    notes: "Order {$order->order_number}",
                );
            }
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return;
            }

            throw $exception;
        }
    }

    public function receivePurchase(Purchase $purchase, User $user): void
    {
        if ($purchase->received_at !== null) {
            throw new DomainException('This purchase has already been received.');
        }

        $purchase->load('items.inventoryItem');

        foreach ($purchase->items as $line) {
            $this->move(
                item: $line->inventoryItem,
                type: InventoryTransactionType::Purchase,
                quantity: (string) $line->quantity,
                user: $user,
                unitCost: (string) $line->unit_cost,
                referenceType: 'purchase_item',
                referenceId: $line->id,
                notes: "Purchase {$purchase->purchase_number}",
            );

            $item = $line->inventoryItem->refresh();
            $item->average_cost = (string) $line->unit_cost;
            $item->save();
        }

        $purchase->received_at = now();
        $purchase->save();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = $exception->getMessage();

        return $code === '23000'
            || str_contains($message, 'UNIQUE')
            || str_contains($message, 'unique');
    }
}
