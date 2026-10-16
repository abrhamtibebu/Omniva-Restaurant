<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\RoleSlug;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\BonoBalance;
use App\Models\BonoHandover;
use App\Models\BonoLedgerEntry;
use App\Models\BonoType;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\BonoHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BonoController extends Controller
{
    use ResolvesBranch;

    public function __construct(private BonoHandoverService $handovers) {}

    public function types(Request $request): JsonResponse
    {
        $this->canAny($request, [
            Permission::BonosTypesManage,
            Permission::BonosIssue,
            Permission::BonosRequest,
            Permission::BonosConfirm,
            Permission::BarRedeem,
            Permission::BarClose,
        ]);

        $types = BonoType::query()
            ->with(['menuItems:id,category_id,name,base_price,active'])
            ->where('branch_id', $this->branchId())
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function storeType(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosTypesManage), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'menu_item_ids' => ['required', 'array', 'min:1'],
            'menu_item_ids.*' => ['integer', 'distinct', 'exists:menu_items,id'],
            'unit_value' => ['required', 'numeric', 'min:0'],
            'redemption_department' => ['required', 'in:bar,kitchen,any'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $menuItemIds = $this->assertMenuItemsOnBranch($data['menu_item_ids']);

        $type = BonoType::query()->create([
            'branch_id' => $this->branchId(),
            'name' => $data['name'],
            'code' => ($data['code'] ?? null) ?: null,
            'unit_value' => $data['unit_value'],
            'redemption_department' => $data['redemption_department'],
            'active' => $data['active'] ?? true,
        ]);

        $type->menuItems()->sync($menuItemIds);

        return response()->json(['data' => $type->load('menuItems:id,category_id,name,base_price,active')], 201);
    }

    public function updateType(Request $request, BonoType $bonoType): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosTypesManage), 403);
        abort_unless($bonoType->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'menu_item_ids' => ['sometimes', 'array', 'min:1'],
            'menu_item_ids.*' => ['integer', 'distinct', 'exists:menu_items,id'],
            'unit_value' => ['sometimes', 'numeric', 'min:0'],
            'redemption_department' => ['sometimes', 'in:bar,kitchen,any'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('menu_item_ids', $data)) {
            $menuItemIds = $this->assertMenuItemsOnBranch($data['menu_item_ids']);
            $bonoType->menuItems()->sync($menuItemIds);
            unset($data['menu_item_ids']);
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = $data['code'] ?: null;
        }

        if ($data !== []) {
            $bonoType->update($data);
        }

        return response()->json(['data' => $bonoType->fresh()->load('menuItems:id,category_id,name,base_price,active')]);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function assertMenuItemsOnBranch(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $branchId = $this->branchId();

        $count = MenuItem::query()
            ->whereIn('id', $ids)
            ->whereHas('category', fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'menu_item_ids' => ['Every menu item must belong to this branch.'],
            ]);
        }

        return $ids;
    }

    public function balances(Request $request): JsonResponse
    {
        $this->canAny($request, [Permission::BonosIssue, Permission::BonosRequest, Permission::BonosTypesManage, Permission::OperationsView, Permission::BonosReconcile]);

        $rows = BonoBalance::query()
            ->with('bonoType')
            ->where('branch_id', $this->branchId())
            ->orderBy('bono_type_id')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $this->canAny($request, [Permission::BonosIssue, Permission::BonosTypesManage, Permission::ReportsView, Permission::OperationsView]);

        $entries = BonoLedgerEntry::query()
            ->with('bonoType', 'actor:id,name')
            ->where('branch_id', $this->branchId())
            ->latest()
            ->limit(200)
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function opening(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosTypesManage), 403);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.bono_type_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $this->handovers->openingBalance($request->user(), $this->branchId(), $data['lines']);

        return response()->json(['message' => 'Opening balance recorded.']);
    }

    public function people(Request $request): JsonResponse
    {
        $this->canAny($request, [Permission::BonosIssue, Permission::BonosRequest, Permission::BonosConfirm]);

        $role = $request->string('role')->toString();
        $slugs = match ($role) {
            'cashier' => [RoleSlug::Cashier->value, RoleSlug::Admin->value],
            'floor' => [RoleSlug::Waiter->value, RoleSlug::Barman->value],
            default => [
                RoleSlug::Cashier->value,
                RoleSlug::Admin->value,
                RoleSlug::Waiter->value,
                RoleSlug::Barman->value,
            ],
        };

        $people = User::query()
            ->with('role:id,slug,name')
            ->where('branch_id', $this->branchId())
            ->where('active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'branch_id']);

        return response()->json(['data' => $people]);
    }

    public function handovers(Request $request): JsonResponse
    {
        $this->canAny($request, [Permission::BonosIssue, Permission::BonosRequest, Permission::BonosConfirm, Permission::BonosReconcile]);

        $query = BonoHandover::query()
            ->with('lines.bonoType', 'issuer:id,name', 'receiver:id,name')
            ->where('branch_id', $this->branchId())
            ->latest();

        $user = $request->user();
        if ($user->hasPermission(Permission::BonosRequest) && ! $user->hasPermission(Permission::BonosIssue) && ! $user->isAdmin()) {
            $query->where(fn ($q) => $q->where('receiver_id', $user->id)->orWhere('issuer_id', $user->id));
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function storeHandover(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:issue,request,return'],
            'receiver_id' => ['required_if:kind,issue', 'required_if:kind,return', 'nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.bono_type_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        if ($data['kind'] === 'request') {
            abort_unless($request->user()->hasPermission(Permission::BonosRequest), 403);
            $handover = $this->handovers->request($request->user(), $this->branchId(), $data['lines'], $data['notes'] ?? null);
        } elseif ($data['kind'] === 'return') {
            abort_unless($request->user()->hasPermission(Permission::BonosRequest), 403);
            $receiver = User::query()->findOrFail($data['receiver_id']);
            abort_unless((int) $receiver->branch_id === $this->branchId() || $request->user()->canOperateBranch((int) $receiver->branch_id), 422);
            $handover = $this->handovers->returnUnused(
                $request->user(),
                $this->branchId(),
                (int) $data['receiver_id'],
                $data['lines'],
                $data['idempotency_key'] ?? null,
                $data['notes'] ?? null,
            );
        } else {
            abort_unless($request->user()->hasPermission(Permission::BonosIssue), 403);
            $receiver = User::query()->findOrFail($data['receiver_id']);
            abort_unless((int) $receiver->branch_id === $this->branchId() || $request->user()->canOperateBranch((int) $receiver->branch_id), 422);
            $handover = $this->handovers->issue(
                $request->user(),
                $this->branchId(),
                (int) $data['receiver_id'],
                $data['lines'],
                $data['idempotency_key'] ?? null,
                $data['notes'] ?? null,
            );
        }

        return response()->json(['data' => $handover], 201);
    }

    public function approve(Request $request, BonoHandover $bonoHandover): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BonosIssue)
            || $request->user()->isAdmin()
            || $request->user()->isManager(),
            403,
        );
        $this->owns($bonoHandover);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.approved_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->handovers->approve($bonoHandover->load('lines'), $request->user(), $data['lines'])]);
    }

    public function reject(Request $request, BonoHandover $bonoHandover): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BonosIssue)
            || $request->user()->isAdmin()
            || $request->user()->isManager(),
            403,
        );
        $this->owns($bonoHandover);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $this->handovers->reject($bonoHandover, $request->user(), $data['reason'])]);
    }

    public function issue(Request $request, BonoHandover $bonoHandover): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BonosIssue)
            || $request->user()->isAdmin()
            || $request->user()->isManager(),
            403,
        );
        $this->owns($bonoHandover);

        $data = $request->validate([
            'lines' => ['sometimes', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.issued_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->handovers->issueApproved($bonoHandover->load('lines'), $request->user(), $data['lines'] ?? [])]);
    }

    public function confirm(Request $request, BonoHandover $bonoHandover): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosConfirm), 403);
        $this->owns($bonoHandover);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->handovers->confirm($bonoHandover->load('lines'), $request->user(), $data['lines'])]);
    }

    private function owns(BonoHandover $handover): void
    {
        abort_unless($handover->branch_id === $this->branchId(), 404);
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function canAny(Request $request, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }
}
