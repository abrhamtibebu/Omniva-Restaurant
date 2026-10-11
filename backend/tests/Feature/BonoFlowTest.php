<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\BonoBalance;
use App\Models\BonoType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BonoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_issue_is_confirmed_once_and_a_short_count_opens_a_discrepancy(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $type = $this->bonoType();

        $this->postJson('/api/v1/bono/opening-balances', [
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '100']],
        ])->assertOk();

        $handoverId = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $cashier->id,
            'idempotency_key' => 'issue-100',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '100']],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $cashier->id,
            'idempotency_key' => 'issue-100',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '100']],
        ])->assertCreated();

        $this->assertSame('0.000', $this->balance($type->id, 'central', 0));

        Sanctum::actingAs($cashier);
        $lineId = $this->getJson('/api/v1/bono/handovers')->json('data.0.lines.0.id');

        $this->postJson("/api/v1/bono/handovers/{$handoverId}/confirm", [
            'lines' => [['id' => $lineId, 'received_quantity' => '98']],
        ])->assertOk()->assertJsonPath('data.status', 'discrepancy_review');

        $this->assertSame('98.000', $this->balance($type->id, 'user', $cashier->id));
        $this->assertDatabaseHas('discrepancy_cases', ['status' => 'open', 'type' => 'bono_handover']);
        unset($admin);
    }

    public function test_cashier_cannot_issue_more_than_their_balance(): void
    {
        $cashier = $this->actingAsRole(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();

        $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $waiter->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '5']],
        ])->assertStatus(422);

        unset($cashier);
    }

    public function test_partial_approval_only_assigns_the_confirmed_quantity(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();

        $this->postJson('/api/v1/bono/opening-balances', [
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '20']],
        ])->assertOk();

        $toCashier = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $cashier->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '20']],
        ])->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$toCashier['id'].'/confirm', [
            'lines' => [['id' => $toCashier['lines'][0]['id'], 'received_quantity' => '20']],
        ])->assertOk();

        Sanctum::actingAs($waiter);
        $request = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'request',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '10']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/approve', [
            'lines' => [['id' => $request['lines'][0]['id'], 'approved_quantity' => '4']],
        ])->assertOk();
        $issued = $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/issue', [
            'lines' => [['id' => $request['lines'][0]['id'], 'issued_quantity' => '4']],
        ])->assertOk()->json('data');

        Sanctum::actingAs($waiter);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '4']],
        ])->assertOk();

        $this->assertSame('4.000', $this->balance($type->id, 'user', $waiter->id));
        unset($admin);
    }

    public function test_waiter_close_splits_issued_sold_returned_and_keeps_transfers_unverified(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();
        $this->fundCashier($admin, $cashier, $type, '30');

        Sanctum::actingAs($cashier);
        $issued = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $waiter->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '30']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($waiter);
        $this->postJson('/api/v1/bono/handovers/'.$issued['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '30']],
        ])->assertOk();

        $close = $this->postJson('/api/v1/bono/reconciliations', [
            'department' => 'waiter',
            'cash_amount' => '1600',
            'lines' => [[
                'bono_type_id' => $type->id,
                'sold_quantity' => '22',
                'returned_quantity' => '7',
            ]],
            'transfers' => [[
                'method' => 'mobile_money',
                'amount' => '500',
                'reference' => 'TX-1',
            ]],
        ])->assertCreated();

        $close->assertJsonPath('data.lines.0.unaccounted_quantity', '1.000');
        $this->assertSame('pending', $close->json('data.evidence.0.verification_status'));

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/reconciliations/'.$close->json('data.id').'/accept')->assertStatus(422);

        $this->postJson('/api/v1/bono/reconciliations/'.$close->json('data.id').'/verify', [
            'verified_cash' => '1600',
            'lines' => [[
                'id' => $close->json('data.lines.0.id'),
                'verified_quantity' => '7',
            ]],
            'transfers' => [[
                'id' => $close->json('data.evidence.0.id'),
                'verification_status' => 'verified',
            ]],
        ])->assertOk();

        $this->postJson('/api/v1/bono/reconciliations/'.$close->json('data.id').'/flag', [
            'reason' => 'One bono is missing.',
        ])->assertOk()->assertJsonPath('data.status', 'discrepancy');
    }

    public function test_waiter_return_moves_unused_bonos_back_to_cashier(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();
        $this->fundCashier($admin, $cashier, $type, '20');

        Sanctum::actingAs($cashier);
        $issued = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $waiter->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '10']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($waiter);
        $this->postJson('/api/v1/bono/handovers/'.$issued['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '10']],
        ])->assertOk();

        $this->assertSame('10.000', $this->balance($type->id, 'user', $waiter->id));
        $this->assertSame('10.000', $this->balance($type->id, 'user', $cashier->id));

        $returned = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'return',
            'receiver_id' => $cashier->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '4']],
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'waiter_return')
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->json('data');

        $this->assertSame('6.000', $this->balance($type->id, 'user', $waiter->id));

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$returned['id'].'/confirm', [
            'lines' => [['id' => $returned['lines'][0]['id'], 'received_quantity' => '4']],
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('6.000', $this->balance($type->id, 'user', $waiter->id));
        $this->assertSame('14.000', $this->balance($type->id, 'user', $cashier->id));
    }

    public function test_barman_can_return_unused_bonos_to_cashier(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $barman = $this->user(RoleSlug::Barman);
        $type = $this->bonoType();
        $this->fundCashier($admin, $cashier, $type, '8');

        Sanctum::actingAs($cashier);
        $issued = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $barman->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '5']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($barman);
        $this->postJson('/api/v1/bono/handovers/'.$issued['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '5']],
        ])->assertOk();

        $returned = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'return',
            'receiver_id' => $cashier->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '2']],
        ])->assertCreated()->assertJsonPath('data.kind', 'bar_return')->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$returned['id'].'/confirm', [
            'lines' => [['id' => $returned['lines'][0]['id'], 'received_quantity' => '2']],
        ])->assertOk();

        $this->assertSame('3.000', $this->balance($type->id, 'user', $barman->id));
        $this->assertSame('5.000', $this->balance($type->id, 'user', $cashier->id));
    }

    public function test_cashier_reject_leaves_no_custody_change(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();
        $this->fundCashier($admin, $cashier, $type, '10');

        Sanctum::actingAs($waiter);
        $request = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'request',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '5']],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/reject', [
            'reason' => 'Not enough stock for service.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame('10.000', $this->balance($type->id, 'user', $cashier->id));
        $this->assertSame('', $this->balance($type->id, 'user', $waiter->id));
    }

    public function test_cashier_request_is_approved_by_admin_with_adjusted_quantity(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $type = $this->bonoType();

        $this->postJson('/api/v1/bono/opening-balances', [
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '100']],
        ])->assertOk();

        Sanctum::actingAs($cashier);
        $request = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'request',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '50']],
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'cashier_request')
            ->json('data');

        // Peer cashiers cannot approve cashier requests.
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/approve', [
            'lines' => [['id' => $request['lines'][0]['id'], 'approved_quantity' => '30']],
        ])->assertStatus(422);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/approve', [
            'lines' => [['id' => $request['lines'][0]['id'], 'approved_quantity' => '30']],
        ])->assertOk()->assertJsonPath('data.status', 'partially_approved');

        $issued = $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/issue', [
            'lines' => [['id' => $request['lines'][0]['id'], 'issued_quantity' => '30']],
        ])->assertOk()->json('data');

        $this->assertSame('70.000', $this->balance($type->id, 'central', 0));

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '30']],
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('30.000', $this->balance($type->id, 'user', $cashier->id));
    }

    public function test_waiter_request_cashier_can_adjust_quantity_before_issue(): void
    {
        $admin = $this->actingAsRole(RoleSlug::Admin);
        $cashier = $this->user(RoleSlug::Cashier);
        $waiter = $this->user(RoleSlug::Waiter);
        $type = $this->bonoType();
        $this->fundCashier($admin, $cashier, $type, '30');

        Sanctum::actingAs($waiter);
        $request = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'request',
            'lines' => [['bono_type_id' => $type->id, 'quantity' => '50']],
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'waiter_request')
            ->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/approve', [
            'lines' => [['id' => $request['lines'][0]['id'], 'approved_quantity' => '30']],
        ])->assertOk();

        $issued = $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/issue', [
            'lines' => [['id' => $request['lines'][0]['id'], 'issued_quantity' => '30']],
        ])->assertOk()->json('data');

        $this->assertSame('0.000', $this->balance($type->id, 'user', $cashier->id));

        Sanctum::actingAs($waiter);
        $this->postJson('/api/v1/bono/handovers/'.$request['id'].'/confirm', [
            'lines' => [['id' => $issued['lines'][0]['id'], 'received_quantity' => '30']],
        ])->assertOk();

        $this->assertSame('30.000', $this->balance($type->id, 'user', $waiter->id));
    }

    private function bonoType(): BonoType
    {
        return BonoType::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Beer',
            'unit_value' => '80.00',
            'redemption_department' => 'bar',
            'active' => true,
        ]);
    }

    private function fundCashier(User $admin, User $cashier, BonoType $type, string $quantity): void
    {
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bono/opening-balances', [
            'lines' => [['bono_type_id' => $type->id, 'quantity' => $quantity]],
        ])->assertOk();

        $handover = $this->postJson('/api/v1/bono/handovers', [
            'kind' => 'issue',
            'receiver_id' => $cashier->id,
            'lines' => [['bono_type_id' => $type->id, 'quantity' => $quantity]],
        ])->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/bono/handovers/'.$handover['id'].'/confirm', [
            'lines' => [['id' => $handover['lines'][0]['id'], 'received_quantity' => $quantity]],
        ])->assertOk();
    }

    private function balance(int $typeId, string $holderType, int $holderId): string
    {
        $row = BonoBalance::query()
            ->where('bono_type_id', $typeId)
            ->where('holder_type', $holderType)
            ->where('holder_id', $holderId)
            ->first();

        return (string) $row?->quantity;
    }
}
