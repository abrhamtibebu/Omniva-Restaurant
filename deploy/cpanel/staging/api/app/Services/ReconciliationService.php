<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReconciliationStatus;
use App\Enums\StockLocationType;
use App\Enums\TransferStatus;
use App\Exceptions\DomainException;
use App\Models\BonoHandover;
use App\Models\BonoType;
use App\Models\DiscrepancyCase;
use App\Models\Reconciliation;
use App\Models\StockBalance;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        private SequenceService $sequences,
        private BonoLedger $ledger,
        private BonoHandoverService $handovers,
        private StockControlService $stock,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{bono_type_id?: int, inventory_item_id?: int, sold_quantity?: string|int|float, returned_quantity?: string|int|float, physical_quantity?: string|int|float}>  $lines
     * @param  list<array{method: string, amount: string|int|float, reference?: string|null}>  $transfers
     */
    public function submit(User $actor, int $branchId, string $department, array $lines, string $cash, array $transfers, ?string $notes): Reconciliation
    {
        $session = $this->handovers->openSession($actor, $branchId, $department);

        $blocking = Reconciliation::query()
            ->where('department_session_id', $session->id)
            ->whereNotIn('status', [ReconciliationStatus::Returned->value, ReconciliationStatus::Closed->value])
            ->exists();

        if ($blocking) {
            throw new DomainException('This shift already has a reconciliation in progress.');
        }

        $reconciliation = DB::transaction(function () use ($actor, $branchId, $department, $lines, $cash, $transfers, $notes, $session) {
            $expectedValue = '0.00';
            $reconciliation = Reconciliation::query()->create([
                'branch_id' => $branchId,
                'reference' => $this->sequences->nextReconciliationNumber($branchId),
                'department' => $department,
                'department_session_id' => $session->id,
                'submitted_by' => $actor->id,
                'status' => ReconciliationStatus::Submitted,
                'submitted_cash' => Money::of($cash),
                'notes' => $notes,
                'submitted_at' => now(),
            ]);

            foreach ($lines as $line) {
                if (! empty($line['bono_type_id'])) {
                    $type = BonoType::query()->where('branch_id', $branchId)->findOrFail($line['bono_type_id']);
                    $issued = $this->issuedQuantity($session->id, (int) $actor->id, $type->id);
                    $sold = Qty::of((string) ($line['sold_quantity'] ?? '0'));
                    $returned = Qty::of((string) ($line['returned_quantity'] ?? '0'));
                    $unaccounted = Qty::sub($issued, Qty::add($sold, $returned));
                    $value = Money::multiply((string) $type->unit_value, $sold);
                    $expectedValue = Money::add($expectedValue, $value);

                    $reconciliation->lines()->create([
                        'line_kind' => 'bono',
                        'bono_type_id' => $type->id,
                        'issued_quantity' => $issued,
                        'sold_quantity' => $sold,
                        'returned_quantity' => $returned,
                        'unaccounted_quantity' => $unaccounted,
                        'expected_value' => $value,
                    ]);
                }

                if (! empty($line['inventory_item_id'])) {
                    $locationType = $department === 'bar' ? StockLocationType::Bar : StockLocationType::Kitchen;
                    $location = $this->stock->location($branchId, $locationType);
                    $expected = StockBalance::query()
                        ->where('inventory_item_id', $line['inventory_item_id'])
                        ->where('stock_location_id', $location->id)
                        ->value('quantity');
                    $physical = Qty::of((string) ($line['physical_quantity'] ?? '0'));
                    $expectedQty = Qty::of($expected ?? '0');

                    $reconciliation->lines()->create([
                        'line_kind' => 'stock',
                        'inventory_item_id' => $line['inventory_item_id'],
                        'expected_quantity' => $expectedQty,
                        'physical_quantity' => $physical,
                        'unaccounted_quantity' => Qty::sub($physical, $expectedQty),
                    ]);
                }
            }

            foreach ($transfers as $transfer) {
                $reconciliation->evidence()->create([
                    'branch_id' => $branchId,
                    'uploaded_by' => $actor->id,
                    'method' => $transfer['method'],
                    'amount' => Money::of((string) $transfer['amount']),
                    'reference' => $transfer['reference'] ?? null,
                    'transaction_reference' => $transfer['reference'] ?? null,
                    'ocr_status' => 'pending',
                    'verification_status' => 'pending',
                ]);
            }

            $reconciliation->expected_value = $expectedValue;
            $reconciliation->save();

            return $reconciliation;
        });

        $this->audit->record('reconciliation.submitted', $reconciliation, null, ['department' => $department], $actor);

        return $reconciliation->load('lines.bonoType', 'lines.item', 'evidence', 'submitter');
    }

    /**
     * @param  list<array{id: int, verified_quantity?: string|int|float|null}>  $lines
     * @param  list<array{id: int, verification_status: string}>  $transfers
     */
    public function verify(Reconciliation $reconciliation, User $cashier, array $lines, ?string $verifiedCash, array $transfers): Reconciliation
    {
        $this->guardMutable($reconciliation);
        $byLine = collect($lines)->keyBy('id');
        $byTransfer = collect($transfers)->keyBy('id');

        foreach ($reconciliation->lines as $line) {
            if (! isset($byLine[$line->id])) {
                continue;
            }

            $line->verified_quantity = Qty::of((string) $byLine[$line->id]['verified_quantity']);
            $line->save();
        }

        if ($verifiedCash !== null) {
            $reconciliation->verified_cash = Money::of($verifiedCash);
        }

        foreach ($reconciliation->evidence as $evidence) {
            if (! isset($byTransfer[$evidence->id])) {
                continue;
            }

            $status = $byTransfer[$evidence->id]['verification_status'];
            $evidence->verification_status = $status;
            $evidence->verified_by = $status === 'verified' ? $cashier->id : null;
            $evidence->verified_at = $status === 'verified' ? now() : null;
            $evidence->save();
        }

        $reconciliation->status = ReconciliationStatus::UnderReview;
        $reconciliation->reviewer_id = $cashier->id;
        $reconciliation->save();

        $this->audit->record('reconciliation.verified', $reconciliation, null, [
            'verified_cash' => $reconciliation->verified_cash,
        ], $cashier);

        return $reconciliation->fresh('lines', 'evidence');
    }

    public function accept(Reconciliation $reconciliation, User $cashier): Reconciliation
    {
        $this->guardMutable($reconciliation);
        $reconciliation->load('lines', 'evidence');

        if (! $this->isClean($reconciliation)) {
            throw new DomainException('This close has a variance. Flag it instead of accepting it.');
        }

        return $this->finalize($reconciliation, $cashier, ReconciliationStatus::Closed);
    }

    public function flag(Reconciliation $reconciliation, User $cashier, string $reason): Reconciliation
    {
        $this->guardMutable($reconciliation);

        DB::transaction(function () use ($reconciliation, $cashier, $reason) {
            foreach ($reconciliation->lines as $line) {
                if (Qty::eq((string) $line->unaccounted_quantity, '0')) {
                    continue;
                }

                DiscrepancyCase::query()->create([
                    'branch_id' => $reconciliation->branch_id,
                    'type' => 'reconciliation',
                    'reference_type' => Reconciliation::class,
                    'reference_id' => $reconciliation->id,
                    'expected_quantity' => $line->issued_quantity ?: $line->expected_quantity,
                    'actual_quantity' => $line->physical_quantity ?? Qty::add((string) $line->sold_quantity, (string) $line->returned_quantity),
                    'variance_quantity' => $line->unaccounted_quantity,
                    'reason' => $reason,
                    'status' => 'open',
                    'opened_by' => $cashier->id,
                ]);
            }

            if ($reconciliation->verified_cash !== null && Money::compare((string) $reconciliation->verified_cash, (string) $reconciliation->submitted_cash) !== 0) {
                DiscrepancyCase::query()->create([
                    'branch_id' => $reconciliation->branch_id,
                    'type' => 'cash',
                    'reference_type' => Reconciliation::class,
                    'reference_id' => $reconciliation->id,
                    'expected_value' => $reconciliation->submitted_cash,
                    'actual_value' => $reconciliation->verified_cash,
                    'reason' => $reason,
                    'status' => 'open',
                    'opened_by' => $cashier->id,
                ]);
            }

            $reconciliation->status = ReconciliationStatus::Discrepancy;
            $reconciliation->reviewer_id = $cashier->id;
            $reconciliation->notes = trim(($reconciliation->notes ? $reconciliation->notes."\n" : '').$reason);
            $reconciliation->save();
        });

        $this->audit->record('reconciliation.flagged', $reconciliation, null, ['reason' => $reason], $cashier);

        return $reconciliation->fresh('lines', 'evidence');
    }

    public function returnForCorrection(Reconciliation $reconciliation, User $cashier, string $reason): Reconciliation
    {
        $this->guardMutable($reconciliation);
        $reconciliation->status = ReconciliationStatus::Returned;
        $reconciliation->notes = trim(($reconciliation->notes ? $reconciliation->notes."\n" : '').$reason);
        $reconciliation->reviewer_id = $cashier->id;
        $reconciliation->save();
        $this->audit->record('reconciliation.returned', $reconciliation, null, ['reason' => $reason], $cashier);

        return $reconciliation;
    }

    public function close(Reconciliation $reconciliation, User $actor): Reconciliation
    {
        if ($reconciliation->status->isClosed()) {
            throw new DomainException('This reconciliation is already closed.');
        }

        if ($reconciliation->status !== ReconciliationStatus::Discrepancy) {
            throw new DomainException('Only a discrepancy review can be closed this way.');
        }

        $open = DiscrepancyCase::query()
            ->where('reference_type', Reconciliation::class)
            ->where('reference_id', $reconciliation->id)
            ->where('status', 'open')
            ->exists();

        if ($open) {
            throw new DomainException('Resolve the open discrepancies before closing.');
        }

        return $this->finalize($reconciliation, $actor, ReconciliationStatus::Closed);
    }

    /**
     * @param  array{holder_type?: string, holder_id?: int, bono_type_id?: int, inventory_item_id?: int, location_id?: int, direction?: string, quantity?: string|int|float}|null  $adjustment
     */
    public function resolve(DiscrepancyCase $case, User $actor, string $resolution, ?array $adjustment): DiscrepancyCase
    {
        if ($case->status === 'resolved') {
            throw new DomainException('This discrepancy is already resolved.');
        }

        DB::transaction(function () use ($case, $actor, $resolution, $adjustment) {
            if ($adjustment && ! empty($adjustment['quantity'])) {
                $qty = (string) $adjustment['quantity'];
                $direction = $adjustment['direction'] ?? 'out';

                if (! empty($adjustment['bono_type_id'])) {
                    $method = $direction === 'in' ? 'credit' : 'debit';
                    $this->ledger->{$method}(
                        $case->branch_id,
                        (int) $adjustment['bono_type_id'],
                        $adjustment['holder_type'] ?? BonoLedger::USER,
                        (int) ($adjustment['holder_id'] ?? 0),
                        $qty,
                        'adjustment',
                        DiscrepancyCase::class,
                        $case->id,
                        "discrepancy:{$case->id}:bono",
                        $actor->id,
                        $resolution,
                    );
                }

                if (! empty($adjustment['inventory_item_id']) && ! empty($adjustment['location_id'])) {
                    $this->stock->adjustBalance(
                        (int) $adjustment['inventory_item_id'],
                        (int) $adjustment['location_id'],
                        Qty::of($qty),
                        $direction === 'in' ? 'in' : 'out',
                    );
                }
            }

            $case->status = 'resolved';
            $case->resolution = $resolution;
            $case->resolved_by = $actor->id;
            $case->resolved_at = now();
            $case->save();
        });

        $this->audit->record('discrepancy.resolved', $case, null, ['resolution' => $resolution, 'adjustment' => $adjustment], $actor);

        return $case->fresh();
    }

    private function finalize(Reconciliation $reconciliation, User $actor, ReconciliationStatus $status): Reconciliation
    {
        DB::transaction(function () use ($reconciliation, $actor, $status) {
            if ($reconciliation->department === 'waiter') {
                foreach ($reconciliation->lines()->where('line_kind', 'bono')->get() as $line) {
                    $sold = (string) $line->sold_quantity;
                    $returned = (string) $line->returned_quantity;

                    if (Qty::isPositive($sold)) {
                        $this->ledger->debit(
                            $reconciliation->branch_id,
                            (int) $line->bono_type_id,
                            BonoLedger::USER,
                            (int) $reconciliation->submitted_by,
                            $sold,
                            'sold',
                            Reconciliation::class,
                            $reconciliation->id,
                            "reconciliation:{$reconciliation->id}:sold:{$line->id}",
                            $actor->id,
                        );
                    }

                    if (Qty::isPositive($returned)) {
                        $this->ledger->debit(
                            $reconciliation->branch_id,
                            (int) $line->bono_type_id,
                            BonoLedger::USER,
                            (int) $reconciliation->submitted_by,
                            $returned,
                            'return',
                            Reconciliation::class,
                            $reconciliation->id,
                            "reconciliation:{$reconciliation->id}:return-out:{$line->id}",
                            $actor->id,
                        );
                        $this->ledger->credit(
                            $reconciliation->branch_id,
                            (int) $line->bono_type_id,
                            BonoLedger::USER,
                            $actor->id,
                            $returned,
                            'return',
                            Reconciliation::class,
                            $reconciliation->id,
                            "reconciliation:{$reconciliation->id}:return-in:{$line->id}",
                            $actor->id,
                        );
                    }
                }
            }

            $reconciliation->status = $status;
            $reconciliation->closed_at = now();
            $reconciliation->reviewer_id = $actor->id;
            $reconciliation->save();

            $reconciliation->session?->update(['status' => 'closed', 'closed_at' => now()]);
        });

        $this->audit->record('reconciliation.closed', $reconciliation, null, ['status' => $status->value], $actor);

        return $reconciliation->fresh('lines', 'evidence');
    }

    private function isClean(Reconciliation $reconciliation): bool
    {
        foreach ($reconciliation->lines as $line) {
            if (! Qty::eq((string) $line->unaccounted_quantity, '0')) {
                return false;
            }
        }

        if ($reconciliation->verified_cash !== null && Money::compare((string) $reconciliation->verified_cash, (string) $reconciliation->submitted_cash) !== 0) {
            return false;
        }

        foreach ($reconciliation->evidence as $evidence) {
            if ($evidence->verification_status !== 'verified') {
                return false;
            }
        }

        if (Money::compare((string) $reconciliation->submitted_cash, '0') === 1 && $reconciliation->verified_cash === null) {
            return false;
        }

        return true;
    }

    private function guardMutable(Reconciliation $reconciliation): void
    {
        if ($reconciliation->status->isClosed() || $reconciliation->status === ReconciliationStatus::Returned) {
            throw new DomainException('This reconciliation can no longer be edited.');
        }
    }

    private function issuedQuantity(int $sessionId, int $userId, int $bonoTypeId): string
    {
        $sum = BonoHandover::query()
            ->where('department_session_id', $sessionId)
            ->where('receiver_id', $userId)
            ->whereIn('status', [TransferStatus::Confirmed->value, TransferStatus::DiscrepancyReview->value])
            ->whereHas('lines', fn ($q) => $q->where('bono_type_id', $bonoTypeId))
            ->with(['lines' => fn ($q) => $q->where('bono_type_id', $bonoTypeId)])
            ->get()
            ->flatMap->lines
            ->reduce(fn (string $carry, $line) => Qty::add($carry, (string) $line->received_quantity), '0.000');

        return $sum;
    }
}
