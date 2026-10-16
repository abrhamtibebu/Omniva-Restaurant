<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\BonoBalance;
use App\Models\BonoLedgerEntry;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class BonoLedger
{
    public const CENTRAL = 'central';

    public const USER = 'user';

    public function balance(int $branchId, int $bonoTypeId, string $holderType, int $holderId): string
    {
        $quantity = BonoBalance::query()
            ->where('branch_id', $branchId)
            ->where('bono_type_id', $bonoTypeId)
            ->where('holder_type', $holderType)
            ->where('holder_id', $holderId)
            ->value('quantity');

        return Qty::of($quantity ?? '0');
    }

    public function credit(
        int $branchId,
        int $bonoTypeId,
        string $holderType,
        int $holderId,
        string $quantity,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $idempotencyKey,
        ?int $actorId,
        ?string $notes = null,
    ): BonoLedgerEntry {
        return $this->apply('in', $branchId, $bonoTypeId, $holderType, $holderId, $quantity, $movementType, $referenceType, $referenceId, $idempotencyKey, $actorId, $notes);
    }

    public function debit(
        int $branchId,
        int $bonoTypeId,
        string $holderType,
        int $holderId,
        string $quantity,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $idempotencyKey,
        ?int $actorId,
        ?string $notes = null,
    ): BonoLedgerEntry {
        return $this->apply('out', $branchId, $bonoTypeId, $holderType, $holderId, $quantity, $movementType, $referenceType, $referenceId, $idempotencyKey, $actorId, $notes);
    }

    private function apply(
        string $direction,
        int $branchId,
        int $bonoTypeId,
        string $holderType,
        int $holderId,
        string $quantity,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $idempotencyKey,
        ?int $actorId,
        ?string $notes,
    ): BonoLedgerEntry {
        $qty = Qty::of($quantity);

        if (! Qty::isPositive($qty)) {
            throw DomainException::trans('messages.bono.quantity_gt_zero');
        }

        return DB::transaction(function () use ($direction, $branchId, $bonoTypeId, $holderType, $holderId, $qty, $movementType, $referenceType, $referenceId, $idempotencyKey, $actorId, $notes) {
            if ($idempotencyKey) {
                $existing = BonoLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

                if ($existing) {
                    return $existing;
                }
            }

            $balance = BonoBalance::query()
                ->where('branch_id', $branchId)
                ->where('bono_type_id', $bonoTypeId)
                ->where('holder_type', $holderType)
                ->where('holder_id', $holderId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = BonoBalance::query()->create([
                    'branch_id' => $branchId,
                    'bono_type_id' => $bonoTypeId,
                    'holder_type' => $holderType,
                    'holder_id' => $holderId,
                    'quantity' => '0.000',
                ]);
                $balance = BonoBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            }

            if ($direction === 'out' && Qty::gt($qty, (string) $balance->quantity)) {
                throw DomainException::trans('messages.bono.balance_too_low');
            }

            $balance->quantity = $direction === 'in'
                ? Qty::add((string) $balance->quantity, $qty)
                : Qty::sub((string) $balance->quantity, $qty);
            $balance->save();

            return BonoLedgerEntry::query()->create([
                'branch_id' => $branchId,
                'bono_type_id' => $bonoTypeId,
                'holder_type' => $holderType,
                'holder_id' => $holderId,
                'direction' => $direction,
                'quantity' => $qty,
                'movement_type' => $movementType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'actor_id' => $actorId,
            ]);
        });
    }
}
