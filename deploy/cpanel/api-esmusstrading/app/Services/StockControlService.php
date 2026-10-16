<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\StockLocationType;
use App\Enums\TransferStatus;
use App\Exceptions\DomainException;
use App\Models\BarRedemption;
use App\Models\BonoType;
use App\Models\DepartmentSalesSummary;
use App\Models\DiscrepancyCase;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestLine;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class StockControlService
{
    public function __construct(
        private SequenceService $sequences,
        private InventoryService $inventory,
        private BonoLedger $ledger,
        private BonoHandoverService $handovers,
        private AuditLogger $audit,
    ) {}

    public function ensureLocations(int $branchId): void
    {
        foreach (StockLocationType::cases() as $type) {
            StockLocation::query()->firstOrCreate(
                ['branch_id' => $branchId, 'type' => $type->value],
                ['name' => $type->label(), 'active' => true],
            );
        }
    }

    public function location(int $branchId, StockLocationType $type): StockLocation
    {
        $this->ensureLocations($branchId);

        return StockLocation::query()
            ->where('branch_id', $branchId)
            ->where('type', $type->value)
            ->firstOrFail();
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: string|int|float}>  $lines
     */
    public function request(User $requester, int $branchId, StockLocationType $destination, array $lines, ?string $notes, ?string $idempotencyKey): StockRequest
    {
        if ($idempotencyKey) {
            $existing = StockRequest::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('lines.item', 'requester', 'destination');
            }
        }

        $department = $destination === StockLocationType::Bar ? 'bar' : 'kitchen';
        $session = $this->handovers->openSession($requester, $branchId, $department);
        $source = $this->location($branchId, StockLocationType::Store);
        $dest = $this->location($branchId, $destination);

        $request = DB::transaction(function () use ($requester, $branchId, $lines, $notes, $idempotencyKey, $session, $source, $dest) {
            $request = StockRequest::query()->create([
                'branch_id' => $branchId,
                'reference' => $this->sequences->nextStockRequestNumber($branchId),
                'requester_id' => $requester->id,
                'source_location_id' => $source->id,
                'destination_location_id' => $dest->id,
                'department_session_id' => $session->id,
                'status' => TransferStatus::Submitted,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'submitted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = $this->item($branchId, (int) $line['inventory_item_id']);
                $request->lines()->create([
                    'inventory_item_id' => $item->id,
                    'requested_quantity' => Qty::of((string) $line['quantity']),
                ]);
            }

            return $request;
        });

        return $request->load('lines.item', 'requester', 'source', 'destination');
    }

    /**
     * @param  list<array{id: int, approved_quantity: string|int|float}>  $decisions
     */
    public function approve(StockRequest $request, User $actor, array $decisions): StockRequest
    {
        $this->assertOpen($request, [TransferStatus::Submitted]);
        $byId = collect($decisions)->keyBy('id');
        $any = false;
        $partial = false;

        foreach ($request->lines as $line) {
            $approved = Qty::of((string) ($byId[$line->id]['approved_quantity'] ?? $line->requested_quantity));

            if (Qty::gt($approved, (string) $line->requested_quantity)) {
                throw DomainException::trans('messages.stock.approved_exceeds_request');
            }

            $line->approved_quantity = $approved;
            $line->save();
            if (Qty::isPositive($approved)) {
                $any = true;
            }
            if (! Qty::eq($approved, (string) $line->requested_quantity)) {
                $partial = true;
            }
        }

        $request->status = $any
            ? ($partial ? TransferStatus::PartiallyApproved : TransferStatus::Approved)
            : TransferStatus::Rejected;
        $request->save();

        $this->audit->record('stock.approved', $request, null, ['status' => $request->status->value], $actor);

        return $request->fresh('lines.item');
    }

    public function reject(StockRequest $request, User $actor, string $reason): StockRequest
    {
        $this->assertOpen($request, [TransferStatus::Submitted, TransferStatus::Approved, TransferStatus::PartiallyApproved]);
        $request->status = TransferStatus::Rejected;
        $request->notes = trim(($request->notes ? $request->notes."\n" : '').$reason);
        $request->save();
        $this->audit->record('stock.rejected', $request, null, ['reason' => $reason], $actor);

        return $request;
    }

    /**
     * @param  list<array{id: int, issued_quantity: string|int|float}>  $issues
     */
    public function issue(StockRequest $request, User $actor, array $issues): StockRequest
    {
        $this->assertOpen($request, [TransferStatus::Approved, TransferStatus::PartiallyApproved]);
        $byId = collect($issues)->keyBy('id');

        DB::transaction(function () use ($request, $actor, $byId) {
            foreach ($request->lines as $line) {
                $issued = Qty::of((string) ($byId[$line->id]['issued_quantity'] ?? $line->approved_quantity));

                if (Qty::gt($issued, (string) $line->approved_quantity)) {
                    throw DomainException::trans('messages.stock.issued_exceeds_approved');
                }

                $line->issued_quantity = $issued;
                $line->save();

                if (! Qty::isPositive($issued)) {
                    continue;
                }

                $item = InventoryItem::query()->whereKey($line->inventory_item_id)->lockForUpdate()->firstOrFail();

                if (Qty::gt($issued, (string) $item->quantity_on_hand)) {
                    throw DomainException::trans('messages.stock.insufficient', ['name' => $item->name]);
                }

                $this->inventory->move(
                    item: $item,
                    type: InventoryTransactionType::TransferOut,
                    quantity: $issued,
                    user: $actor,
                    referenceType: StockRequestLine::class,
                    referenceId: $line->id,
                    notes: "Stock request {$request->reference}",
                );

                StockMovement::query()->create([
                    'inventory_item_id' => $item->id,
                    'from_location_id' => $request->source_location_id,
                    'type' => 'transfer_out',
                    'quantity' => $issued,
                    'reference_type' => StockRequestLine::class,
                    'reference_id' => $line->id,
                    'idempotency_key' => "stock-request:{$request->id}:issue:{$line->id}",
                    'created_by' => $actor->id,
                ]);
            }

            $request->status = TransferStatus::AwaitingConfirmation;
            $request->issued_at = now();
            $request->save();
        });

        $this->audit->record('stock.issued', $request, null, ['status' => 'awaiting_confirmation'], $actor);

        return $request->fresh('lines.item');
    }

    /**
     * @param  list<array{id: int, received_quantity: string|int|float}>  $receipts
     */
    public function confirm(StockRequest $request, User $receiver, array $receipts): StockRequest
    {
        $this->assertOpen($request, [TransferStatus::AwaitingConfirmation]);

        if ((int) $request->requester_id !== $receiver->id && ! $receiver->isAdmin()) {
            throw DomainException::trans('messages.stock.receiver_must_confirm');
        }

        $byId = collect($receipts)->keyBy('id');

        DB::transaction(function () use ($request, $receiver, $byId) {
            $mismatch = false;

            foreach ($request->lines as $line) {
                $received = Qty::of((string) ($byId[$line->id]['received_quantity'] ?? $line->issued_quantity));

                if (Qty::gt($received, (string) $line->issued_quantity)) {
                    throw DomainException::trans('messages.stock.received_exceeds_issued');
                }

                $line->received_quantity = $received;
                $line->save();

                if (Qty::isPositive($received)) {
                    $this->adjustBalance($line->inventory_item_id, $request->destination_location_id, $received, 'in');
                    StockMovement::query()->create([
                        'inventory_item_id' => $line->inventory_item_id,
                        'to_location_id' => $request->destination_location_id,
                        'type' => 'transfer_in',
                        'quantity' => $received,
                        'reference_type' => StockRequestLine::class,
                        'reference_id' => $line->id,
                        'idempotency_key' => "stock-request:{$request->id}:confirm:{$line->id}",
                        'created_by' => $receiver->id,
                    ]);
                }

                if (! Qty::eq($received, (string) $line->issued_quantity)) {
                    $mismatch = true;
                    DiscrepancyCase::query()->create([
                        'branch_id' => $request->branch_id,
                        'type' => 'stock_receipt',
                        'reference_type' => StockRequestLine::class,
                        'reference_id' => $line->id,
                        'expected_quantity' => $line->issued_quantity,
                        'actual_quantity' => $received,
                        'variance_quantity' => Qty::sub($received, (string) $line->issued_quantity),
                        'reason' => 'Store issued a different quantity than the department received.',
                        'status' => 'open',
                        'opened_by' => $receiver->id,
                    ]);
                }
            }

            $request->status = $mismatch ? TransferStatus::DiscrepancyReview : TransferStatus::Confirmed;
            $request->confirmed_at = now();
            $request->save();
        });

        $this->audit->record('stock.confirmed', $request, null, ['status' => $request->status->value], $receiver);

        return $request->fresh('lines.item');
    }

    public function redeem(
        User $barman,
        int $branchId,
        int $bonoTypeId,
        int $inventoryItemId,
        string $quantity,
        string $idempotencyKey,
        ?int $waiterId,
        ?int $menuItemId = null,
    ): BarRedemption {
        $existing = BarRedemption::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load('bonoType', 'item', 'waiter');
        }

        $type = BonoType::query()
            ->with('menuItems:id')
            ->where('branch_id', $branchId)
            ->whereKey($bonoTypeId)
            ->first();
        $item = $this->item($branchId, $inventoryItemId);

        if (! $type || ! $type->active) {
            throw DomainException::trans('messages.bono.type_not_available');
        }

        $linkedIds = $type->menuItems->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($linkedIds !== []) {
            if (! $menuItemId) {
                throw DomainException::trans('messages.bono.choose_menu_item');
            }
            if (! in_array($menuItemId, $linkedIds, true)) {
                throw DomainException::trans('messages.bono.menu_item_not_covered');
            }
        }

        $bar = $this->location($branchId, StockLocationType::Bar);
        $session = $this->handovers->openSession($barman, $branchId, 'bar');
        $qty = Qty::of($quantity);

        $redemption = DB::transaction(function () use ($barman, $branchId, $type, $item, $qty, $idempotencyKey, $waiterId, $bar, $session) {
            $again = BarRedemption::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($again) {
                return $again;
            }

            $this->adjustBalance($item->id, $bar->id, $qty, 'out');

            StockMovement::query()->create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $bar->id,
                'type' => 'redemption',
                'quantity' => $qty,
                'idempotency_key' => 'redemption:'.$idempotencyKey,
                'created_by' => $barman->id,
            ]);

            $this->ledger->credit(
                $branchId,
                $type->id,
                BonoLedger::USER,
                $barman->id,
                $qty,
                'redemption',
                BarRedemption::class,
                null,
                'bono-redemption:'.$idempotencyKey,
                $barman->id,
            );

            return BarRedemption::query()->create([
                'branch_id' => $branchId,
                'barman_id' => $barman->id,
                'waiter_id' => $waiterId,
                'bono_type_id' => $type->id,
                'inventory_item_id' => $item->id,
                'department_session_id' => $session->id,
                'quantity' => $qty,
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        $this->audit->record('bar.redeemed', $redemption, null, ['quantity' => $qty], $barman);

        return $redemption->load('bonoType', 'item', 'waiter');
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: string|int|float, notes?: string|null}>  $lines
     */
    public function waste(User $actor, int $branchId, StockLocationType $locationType, array $lines): void
    {
        $location = $this->location($branchId, $locationType);

        DB::transaction(function () use ($actor, $lines, $location) {
            foreach ($lines as $index => $line) {
                $qty = Qty::of((string) $line['quantity']);
                $this->adjustBalance((int) $line['inventory_item_id'], $location->id, $qty, 'out');
                StockMovement::query()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'from_location_id' => $location->id,
                    'type' => 'waste',
                    'quantity' => $qty,
                    'notes' => $line['notes'] ?? null,
                    'idempotency_key' => $line['idempotency_key'] ?? null,
                    'created_by' => $actor->id,
                ]);
                unset($index);
            }
        });

        $this->audit->record('stock.waste', $location, null, ['lines' => $lines], $actor);
    }

    /**
     * @param  list<array{inventory_item_id: int, physical_quantity: string|int|float}>  $lines
     */
    public function count(User $actor, int $branchId, StockLocationType $locationType, array $lines, ?string $notes): StockCount
    {
        $location = $this->location($branchId, $locationType);

        $count = DB::transaction(function () use ($actor, $branchId, $location, $lines, $notes) {
            $count = StockCount::query()->create([
                'branch_id' => $branchId,
                'stock_location_id' => $location->id,
                'user_id' => $actor->id,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $balance = StockBalance::query()
                    ->where('inventory_item_id', $line['inventory_item_id'])
                    ->where('stock_location_id', $location->id)
                    ->value('quantity');
                $expected = Qty::of($balance ?? '0');
                $physical = Qty::of((string) $line['physical_quantity']);
                $variance = Qty::sub($physical, $expected);

                $count->lines()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'expected_quantity' => $expected,
                    'physical_quantity' => $physical,
                    'variance_quantity' => $variance,
                ]);

                if (! Qty::eq($variance, '0')) {
                    DiscrepancyCase::query()->create([
                        'branch_id' => $branchId,
                        'type' => 'stock_count',
                        'reference_type' => StockCount::class,
                        'reference_id' => $count->id,
                        'expected_quantity' => $expected,
                        'actual_quantity' => $physical,
                        'variance_quantity' => $variance,
                        'reason' => 'Physical count differs from the ledger.',
                        'status' => 'open',
                        'opened_by' => $actor->id,
                    ]);
                }
            }

            return $count;
        });

        $this->audit->record('stock.counted', $count, null, ['location' => $locationType->value], $actor);

        return $count->load('lines.item', 'location');
    }

    /**
     * @param  list<array{menu_item_id: int, quantity: string|int|float, source?: string}>  $lines
     */
    public function recordKitchenSales(User $actor, int $branchId, array $lines): void
    {
        $session = $this->handovers->openSession($actor, $branchId, 'kitchen');

        foreach ($lines as $line) {
            $menu = MenuItem::query()->findOrFail($line['menu_item_id']);
            $qty = Qty::of((string) $line['quantity']);
            $unit = Money::of((string) $menu->base_price);
            $source = $line['source'] ?? 'manual';

            DepartmentSalesSummary::query()->create([
                'branch_id' => $branchId,
                'department_session_id' => $session->id,
                'business_date' => now()->toDateString(),
                'department' => 'kitchen',
                'menu_item_id' => $menu->id,
                'item_name_snapshot' => $menu->name,
                'quantity' => $qty,
                'unit_price_snapshot' => $unit,
                'value_snapshot' => Money::multiply($unit, $qty),
                'source' => $source === 'pos' ? 'pos' : 'manual',
                'entered_by' => $actor->id,
            ]);
        }
    }

    public function adjustBalance(int $itemId, int $locationId, string $quantity, string $direction): StockBalance
    {
        $qty = Qty::of($quantity);
        $balance = StockBalance::query()
            ->where('inventory_item_id', $itemId)
            ->where('stock_location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = StockBalance::query()->create([
                'inventory_item_id' => $itemId,
                'stock_location_id' => $locationId,
                'quantity' => '0.000',
            ]);
            $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        $balance->quantity = $direction === 'in'
            ? Qty::add((string) $balance->quantity, $qty)
            : Qty::sub((string) $balance->quantity, $qty);
        $balance->save();

        return $balance;
    }

    /**
     * @param  list<TransferStatus>  $allowed
     */
    private function assertOpen(StockRequest $request, array $allowed): void
    {
        if ($request->status->isLocked()) {
            throw DomainException::trans('messages.stock.request_locked');
        }

        if (! in_array($request->status, $allowed, true)) {
            throw DomainException::trans('messages.stock.request_invalid_step', ['status' => $request->status->value]);
        }
    }

    private function item(int $branchId, int $itemId): InventoryItem
    {
        $item = InventoryItem::query()->where('branch_id', $branchId)->whereKey($itemId)->first();

        if (! $item) {
            throw DomainException::trans('messages.stock.item_not_at_branch');
        }

        return $item;
    }
}
