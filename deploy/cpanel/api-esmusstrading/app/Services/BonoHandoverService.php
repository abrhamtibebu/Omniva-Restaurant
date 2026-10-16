<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Enums\RoleSlug;
use App\Enums\TransferStatus;
use App\Exceptions\DomainException;
use App\Models\BonoHandover;
use App\Models\BonoHandoverLine;
use App\Models\BonoType;
use App\Models\DepartmentSession;
use App\Models\DiscrepancyCase;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class BonoHandoverService
{
    public function __construct(
        private BonoLedger $ledger,
        private SequenceService $sequences,
        private AuditLogger $audit,
    ) {}

    public function openSession(User $user, int $branchId, string $department): DepartmentSession
    {
        $existing = DepartmentSession::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $user->id)
            ->where('department', $department)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DepartmentSession::query()->create([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'department' => $department,
            'business_date' => now()->toDateString(),
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    /**
     * @param  list<array{bono_type_id: int, quantity: string|int|float}>  $lines
     */
    public function openingBalance(User $actor, int $branchId, array $lines): void
    {
        DB::transaction(function () use ($actor, $branchId, $lines) {
            foreach ($lines as $line) {
                $type = $this->type($branchId, (int) $line['bono_type_id']);
                $this->ledger->credit(
                    $branchId,
                    $type->id,
                    BonoLedger::CENTRAL,
                    0,
                    (string) $line['quantity'],
                    'opening',
                    null,
                    null,
                    $line['idempotency_key'] ?? null,
                    $actor->id,
                    'Opening bono balance',
                );
            }
        });

        $this->audit->record('bono.opening_balance', $actor, null, ['lines' => $lines], $actor);
    }

    /**
     * @param  list<array{bono_type_id: int, quantity: string|int|float}>  $lines
     */
    public function issue(User $issuer, int $branchId, int $receiverId, array $lines, ?string $idempotencyKey, ?string $notes): BonoHandover
    {
        if ($idempotencyKey) {
            $existing = BonoHandover::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('lines.bonoType', 'issuer', 'receiver');
            }
        }

        $handover = DB::transaction(function () use ($issuer, $branchId, $receiverId, $lines, $idempotencyKey, $notes) {
            $sessionId = null;
            if (! $issuer->isAdmin()) {
                $receiver = User::query()->findOrFail($receiverId);
                $sessionId = $this->openSession($receiver, $branchId, 'waiter')->id;
            }

            $handover = BonoHandover::query()->create([
                'branch_id' => $branchId,
                'reference' => $this->sequences->nextBonoNumber($branchId),
                'kind' => $issuer->isAdmin() ? 'admin_to_cashier' : 'cashier_to_waiter',
                'issuer_id' => $issuer->id,
                'receiver_id' => $receiverId,
                'department_session_id' => $sessionId,
                'status' => TransferStatus::AwaitingConfirmation,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'submitted_at' => now(),
                'issued_at' => now(),
            ]);

            foreach ($lines as $line) {
                $type = $this->type($branchId, (int) $line['bono_type_id']);
                $qty = Qty::of((string) $line['quantity']);
                $row = $handover->lines()->create([
                    'bono_type_id' => $type->id,
                    'requested_quantity' => $qty,
                    'approved_quantity' => $qty,
                    'issued_quantity' => $qty,
                    'received_quantity' => '0.000',
                ]);

                $holderType = $issuer->isAdmin() ? BonoLedger::CENTRAL : BonoLedger::USER;
                $holderId = $issuer->isAdmin() ? 0 : $issuer->id;

                $this->ledger->debit(
                    $branchId,
                    $type->id,
                    $holderType,
                    $holderId,
                    $qty,
                    'issue',
                    BonoHandover::class,
                    $handover->id,
                    "handover:{$handover->id}:issue:{$row->id}",
                    $issuer->id,
                );
            }

            return $handover;
        });

        $this->audit->record('bono.issued', $handover, null, ['receiver_id' => $receiverId], $issuer);

        return $handover->load('lines.bonoType', 'issuer', 'receiver');
    }

    /**
     * @param  list<array{bono_type_id: int, quantity: string|int|float}>  $lines
     */
    public function request(User $requester, int $branchId, array $lines, ?string $notes): BonoHandover
    {
        $requester->loadMissing('role');
        $slug = $requester->role?->slug ?? RoleSlug::Waiter->value;

        $kind = match ($slug) {
            RoleSlug::Cashier->value => 'cashier_request',
            RoleSlug::Barman->value => 'bar_request',
            default => 'waiter_request',
        };
        $department = match ($slug) {
            RoleSlug::Cashier->value => 'cashier',
            RoleSlug::Barman->value => 'bar',
            default => 'waiter',
        };

        $session = $this->openSession($requester, $branchId, $department);

        $handover = DB::transaction(function () use ($requester, $branchId, $lines, $notes, $session, $kind) {
            $handover = BonoHandover::query()->create([
                'branch_id' => $branchId,
                'reference' => $this->sequences->nextBonoNumber($branchId),
                'kind' => $kind,
                'receiver_id' => $requester->id,
                'department_session_id' => $session->id,
                'status' => TransferStatus::Submitted,
                'notes' => $notes,
                'submitted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $type = $this->type($branchId, (int) $line['bono_type_id']);
                $handover->lines()->create([
                    'bono_type_id' => $type->id,
                    'requested_quantity' => Qty::of((string) $line['quantity']),
                ]);
            }

            return $handover;
        });

        return $handover->load('lines.bonoType', 'receiver');
    }

    /**
     * Cashier requests need admin/manager (owner). Floor requests need cashier or owner.
     */
    public function assertCanApprove(BonoHandover $handover, User $actor): void
    {
        if ($actor->isAdmin() || $actor->isManager()) {
            return;
        }

        if ($handover->kind === 'cashier_request') {
            throw DomainException::trans('messages.bono.approve_admin_only');
        }

        if (! $actor->hasPermission(Permission::BonosIssue)) {
            throw DomainException::trans('messages.bono.cannot_approve');
        }

        $actor->loadMissing('role');
        if ($actor->role?->slug !== RoleSlug::Cashier->value) {
            throw DomainException::trans('messages.bono.approve_cashier_or_admin');
        }
    }

    /**
     * Return unused bonos from waiter/bar custody to a cashier.
     * Debits the issuer immediately; credits the cashier on confirm.
     *
     * @param  list<array{bono_type_id: int, quantity: string|int|float}>  $lines
     */
    public function returnUnused(User $issuer, int $branchId, int $receiverId, array $lines, ?string $idempotencyKey, ?string $notes): BonoHandover
    {
        if ($idempotencyKey) {
            $existing = BonoHandover::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('lines.bonoType', 'issuer', 'receiver');
            }
        }

        if ($issuer->id === $receiverId) {
            throw DomainException::trans('messages.bono.cannot_return_to_self');
        }

        $receiver = User::query()->with('role')->findOrFail($receiverId);
        $receiverSlug = $receiver->role?->slug;
        if (! in_array($receiverSlug, [RoleSlug::Cashier->value, RoleSlug::Admin->value], true)) {
            throw DomainException::trans('messages.bono.return_to_cashier');
        }

        if ((int) $receiver->branch_id !== $branchId && ! $issuer->canOperateBranch((int) $receiver->branch_id)) {
            throw DomainException::trans('messages.bono.cashier_not_on_branch');
        }

        $issuer->loadMissing('role');
        $issuerSlug = $issuer->role?->slug ?? 'waiter';
        $kind = $issuerSlug === RoleSlug::Barman->value ? 'bar_return' : 'waiter_return';
        $department = $issuerSlug === RoleSlug::Barman->value ? 'bar' : 'waiter';

        $handover = DB::transaction(function () use ($issuer, $branchId, $receiverId, $lines, $idempotencyKey, $notes, $kind, $department) {
            $session = $this->openSession($issuer, $branchId, $department);

            $handover = BonoHandover::query()->create([
                'branch_id' => $branchId,
                'reference' => $this->sequences->nextBonoNumber($branchId),
                'kind' => $kind,
                'issuer_id' => $issuer->id,
                'receiver_id' => $receiverId,
                'department_session_id' => $session->id,
                'status' => TransferStatus::AwaitingConfirmation,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'submitted_at' => now(),
                'issued_at' => now(),
            ]);

            foreach ($lines as $line) {
                $type = $this->type($branchId, (int) $line['bono_type_id']);
                $qty = Qty::of((string) $line['quantity']);
                $row = $handover->lines()->create([
                    'bono_type_id' => $type->id,
                    'requested_quantity' => $qty,
                    'approved_quantity' => $qty,
                    'issued_quantity' => $qty,
                    'received_quantity' => '0.000',
                ]);

                $this->ledger->debit(
                    $branchId,
                    $type->id,
                    BonoLedger::USER,
                    $issuer->id,
                    $qty,
                    'return',
                    BonoHandover::class,
                    $handover->id,
                    "handover:{$handover->id}:return:{$row->id}",
                    $issuer->id,
                );
            }

            return $handover;
        });

        $this->audit->record('bono.returned', $handover, null, ['receiver_id' => $receiverId], $issuer);

        return $handover->load('lines.bonoType', 'issuer', 'receiver');
    }

    /**
     * @param  list<array{id: int, approved_quantity: string|int|float}>  $decisions
     */
    public function approve(BonoHandover $handover, User $actor, array $decisions): BonoHandover
    {
        $this->assertStatus($handover, [TransferStatus::Submitted]);
        $this->assertCanApprove($handover, $actor);

        $byId = collect($decisions)->keyBy('id');
        $any = false;
        $partial = false;

        foreach ($handover->lines as $line) {
            $approved = Qty::of((string) ($byId[$line->id]['approved_quantity'] ?? $line->requested_quantity));

            if (Qty::gt($approved, (string) $line->requested_quantity)) {
                throw DomainException::trans('messages.bono.approved_exceeds_request');
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

        $handover->status = $any
            ? ($partial ? TransferStatus::PartiallyApproved : TransferStatus::Approved)
            : TransferStatus::Rejected;
        $handover->issuer_id = $actor->id;
        $handover->save();

        $this->audit->record('bono.approved', $handover, null, ['status' => $handover->status->value], $actor);

        return $handover->fresh('lines.bonoType');
    }

    public function reject(BonoHandover $handover, User $actor, string $reason): BonoHandover
    {
        $this->assertStatus($handover, [TransferStatus::Submitted, TransferStatus::Approved, TransferStatus::PartiallyApproved]);
        if ($handover->status === TransferStatus::Submitted) {
            $this->assertCanApprove($handover, $actor);
        }

        $handover->status = TransferStatus::Rejected;
        $handover->notes = trim(($handover->notes ? $handover->notes."\n" : '').$reason);
        $handover->save();

        $this->audit->record('bono.rejected', $handover, null, ['reason' => $reason], $actor);

        return $handover->fresh('lines');
    }

    /**
     * Approve and issue in one transaction so floor staff land on
     * awaiting_confirmation (never stuck at approved if issue fails midway).
     *
     * @param  list<array{id: int, approved_quantity: string|int|float, issued_quantity?: string|int|float}>  $decisions
     */
    public function approveAndIssue(BonoHandover $handover, User $actor, array $decisions): BonoHandover
    {
        return DB::transaction(function () use ($handover, $actor, $decisions) {
            $approved = $this->approve($handover->load('lines'), $actor, $decisions);

            if (in_array($approved->status, [TransferStatus::Rejected], true)) {
                return $approved->load('lines.bonoType', 'issuer', 'receiver');
            }

            $issues = collect($decisions)->map(fn (array $row) => [
                'id' => $row['id'],
                'issued_quantity' => $row['issued_quantity'] ?? $row['approved_quantity'],
            ])->all();

            return $this->issueApproved($approved->load('lines'), $actor, $issues);
        });
    }

    /**
     * @param  list<array{id: int, issued_quantity: string|int|float}>  $issues
     */
    public function issueApproved(BonoHandover $handover, User $actor, array $issues): BonoHandover
    {
        $this->assertStatus($handover, [TransferStatus::Approved, TransferStatus::PartiallyApproved]);
        $this->assertCanApprove($handover, $actor);

        $byId = collect($issues)->keyBy('id');
        // Cashier requests + owner override always leave central.
        // Floor requests prefer the cashier's on-hand custody, then fall back to central
        // so issue is not blocked when the cashier float was never topped up.
        $forceCentral = $handover->kind === 'cashier_request'
            || $actor->isAdmin()
            || $actor->isManager();

        DB::transaction(function () use ($handover, $actor, $byId, $forceCentral) {
            $handover->loadMissing('lines.bonoType');
            $handover->issuer_id = $actor->id;

            foreach ($handover->lines as $line) {
                $issued = Qty::of((string) ($byId[$line->id]['issued_quantity'] ?? $line->approved_quantity));

                if (Qty::gt($issued, (string) $line->approved_quantity)) {
                    throw DomainException::trans('messages.bono.issued_exceeds_approved');
                }

                $line->issued_quantity = $issued;
                $line->save();

                if (! Qty::isPositive($issued)) {
                    continue;
                }

                [$holderType, $holderId] = $this->resolveIssueSource(
                    $handover->branch_id,
                    $line->bono_type_id,
                    (string) $issued,
                    $actor,
                    $forceCentral,
                    $line->bonoType?->name ?? 'bono',
                );

                $this->ledger->debit(
                    $handover->branch_id,
                    $line->bono_type_id,
                    $holderType,
                    $holderId,
                    $issued,
                    'issue',
                    BonoHandover::class,
                    $handover->id,
                    "handover:{$handover->id}:issue:{$line->id}",
                    $actor->id,
                );
            }

            $handover->status = TransferStatus::AwaitingConfirmation;
            $handover->issued_at = now();
            $handover->save();
        });

        $this->audit->record('bono.issued', $handover, null, ['status' => 'awaiting_confirmation'], $actor);

        return $handover->fresh('lines.bonoType', 'issuer', 'receiver');
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveIssueSource(
        int $branchId,
        int $bonoTypeId,
        string $needed,
        User $actor,
        bool $forceCentral,
        string $typeName,
    ): array {
        if ($forceCentral) {
            $central = $this->ledger->balance($branchId, $bonoTypeId, BonoLedger::CENTRAL, 0);
            if (Qty::gt($needed, $central)) {
                throw DomainException::trans('messages.bono.balance_too_low', [
                    'name' => $typeName,
                    'available' => $this->qtyLabel($central),
                    'needed' => $this->qtyLabel($needed),
                    'central' => $this->qtyLabel($central),
                ]);
            }

            return [BonoLedger::CENTRAL, 0];
        }

        $onHand = $this->ledger->balance($branchId, $bonoTypeId, BonoLedger::USER, $actor->id);
        if (! Qty::gt($needed, $onHand)) {
            return [BonoLedger::USER, $actor->id];
        }

        $central = $this->ledger->balance($branchId, $bonoTypeId, BonoLedger::CENTRAL, 0);
        if (! Qty::gt($needed, $central)) {
            return [BonoLedger::CENTRAL, 0];
        }

        throw DomainException::trans('messages.bono.balance_too_low', [
            'name' => $typeName,
            'available' => $this->qtyLabel($onHand),
            'needed' => $this->qtyLabel($needed),
            'central' => $this->qtyLabel($central),
        ]);
    }

    private function qtyLabel(string $qty): string
    {
        return (string) (int) round((float) $qty);
    }

    /**
     * @param  list<array{id: int, received_quantity: string|int|float}>  $receipts
     */
    public function confirm(BonoHandover $handover, User $receiver, array $receipts): BonoHandover
    {
        $this->assertStatus($handover, [TransferStatus::AwaitingConfirmation]);

        if ((int) $handover->receiver_id !== $receiver->id && ! $receiver->isAdmin()) {
            throw DomainException::trans('messages.bono.receiver_must_confirm');
        }

        $byId = collect($receipts)->keyBy('id');
        $mismatch = false;

        DB::transaction(function () use ($handover, $receiver, $byId, &$mismatch) {
            foreach ($handover->lines as $line) {
                $received = Qty::of((string) ($byId[$line->id]['received_quantity'] ?? $line->issued_quantity));

                if (Qty::gt($received, (string) $line->issued_quantity)) {
                    throw DomainException::trans('messages.bono.received_exceeds_issued');
                }

                $line->received_quantity = $received;
                $line->save();

                if (Qty::isPositive($received)) {
                    $this->ledger->credit(
                        $handover->branch_id,
                        $line->bono_type_id,
                        BonoLedger::USER,
                        (int) $handover->receiver_id,
                        $received,
                        'receipt',
                        BonoHandover::class,
                        $handover->id,
                        "handover:{$handover->id}:confirm:{$line->id}",
                        $receiver->id,
                    );
                }

                if (! Qty::eq($received, (string) $line->issued_quantity)) {
                    $mismatch = true;
                    DiscrepancyCase::query()->create([
                        'branch_id' => $handover->branch_id,
                        'type' => 'bono_handover',
                        'reference_type' => BonoHandoverLine::class,
                        'reference_id' => $line->id,
                        'expected_quantity' => $line->issued_quantity,
                        'actual_quantity' => $received,
                        'variance_quantity' => Qty::sub($received, (string) $line->issued_quantity),
                        'reason' => 'Issued and received quantities differ.',
                        'status' => 'open',
                        'opened_by' => $receiver->id,
                    ]);
                }
            }

            $handover->status = $mismatch ? TransferStatus::DiscrepancyReview : TransferStatus::Confirmed;
            $handover->confirmed_at = now();
            $handover->save();
        });

        $this->audit->record('bono.confirmed', $handover, null, ['status' => $handover->status->value], $receiver);

        return $handover->fresh('lines.bonoType');
    }

    /**
     * @param  list<TransferStatus>  $allowed
     */
    private function assertStatus(BonoHandover $handover, array $allowed): void
    {
        if ($handover->status->isLocked()) {
            throw DomainException::trans('messages.bono.handover_locked');
        }

        if (! in_array($handover->status, $allowed, true)) {
            throw DomainException::trans('messages.bono.handover_invalid_step', ['status' => $handover->status->value]);
        }
    }

    private function type(int $branchId, int $typeId): BonoType
    {
        $type = BonoType::query()->where('branch_id', $branchId)->whereKey($typeId)->first();

        if (! $type || ! $type->active) {
            throw DomainException::trans('messages.bono.type_not_at_branch');
        }

        return $type;
    }
}
