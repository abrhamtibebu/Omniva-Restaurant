<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\PurchaseApprovalStatus;
use App\Enums\StockLocationType;
use App\Events\InventoryLow;
use App\Exceptions\DomainException;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Qty;
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
        $this->syncStoreBalance($item);

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

                $kitchen = StockLocation::query()
                    ->where('branch_id', $inventoryItem->branch_id)
                    ->where('type', StockLocationType::Kitchen->value)
                    ->first();
                $kitchenBalance = $kitchen
                    ? StockBalance::query()
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('stock_location_id', $kitchen->id)
                        ->first()
                    : null;

                if ($kitchen && $kitchenBalance) {
                    $locked = StockBalance::query()->whereKey($kitchenBalance->id)->lockForUpdate()->firstOrFail();
                    $locked->quantity = Qty::sub((string) $locked->quantity, $quantity);
                    $locked->save();
                    StockMovement::query()->create([
                        'inventory_item_id' => $inventoryItem->id,
                        'from_location_id' => $kitchen->id,
                        'type' => 'usage',
                        'quantity' => $quantity,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'idempotency_key' => 'order:'.$order->id.':item:'.$inventoryItem->id,
                        'notes' => "Order {$order->order_number}",
                        'created_by' => $user?->id,
                    ]);
                } else {
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
            }
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, string|int|float>  $acceptedByLineId
     */
    public function receivePurchase(Purchase $purchase, User $user, array $acceptedByLineId = []): void
    {
        if ($purchase->received_at !== null) {
            throw DomainException::trans('messages.purchase.already_received');
        }

        $approval = $purchase->approval_status instanceof PurchaseApprovalStatus
            ? $purchase->approval_status
            : PurchaseApprovalStatus::tryFrom((string) $purchase->approval_status);

        if ($approval !== PurchaseApprovalStatus::Approved) {
            throw DomainException::trans('messages.purchase.not_approved');
        }

        $purchase->load('items.inventoryItem');

        foreach ($purchase->items as $line) {
            $ordered = Qty::of((string) $line->quantity);
            $accepted = array_key_exists($line->id, $acceptedByLineId)
                ? Qty::of($acceptedByLineId[$line->id])
                : $ordered;

            if (Qty::gt($accepted, $ordered)) {
                throw DomainException::trans('messages.purchase.accepted_exceeds_ordered');
            }

            $line->accepted_quantity = $accepted;
            $line->rejected_quantity = Qty::sub($ordered, $accepted);
            $line->save();

            if (! Qty::isPositive($accepted)) {
                continue;
            }

            $this->move(
                item: $line->inventoryItem,
                type: InventoryTransactionType::Purchase,
                quantity: $accepted,
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

    private function syncStoreBalance(InventoryItem $item): void
    {
        $store = StockLocation::query()
            ->where('branch_id', $item->branch_id)
            ->where('type', StockLocationType::Store->value)
            ->first();

        if (! $store) {
            return;
        }

        StockBalance::query()->updateOrCreate(
            ['inventory_item_id' => $item->id, 'stock_location_id' => $store->id],
            ['quantity' => $item->quantity_on_hand],
        );
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
