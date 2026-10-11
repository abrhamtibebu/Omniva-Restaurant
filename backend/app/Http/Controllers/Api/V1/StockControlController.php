<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\StockLocationType;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\StockRequest;
use App\Services\StockControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockControlController extends Controller
{
    use ResolvesBranch;

    public function __construct(private StockControlService $stock) {}

    public function locations(Request $request): JsonResponse
    {
        $this->stock->ensureLocations($this->branchId());

        return response()->json([
            'data' => StockLocation::query()->where('branch_id', $this->branchId())->orderBy('id')->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->canAny($request, [Permission::StockRequest, Permission::StockIssue, Permission::StockConfirm]);

        return response()->json([
            'data' => StockRequest::query()
                ->with('lines.item', 'requester:id,name', 'source', 'destination')
                ->where('branch_id', $this->branchId())
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::StockRequest), 403);

        $data = $request->validate([
            'destination' => ['required', 'in:bar,kitchen'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $requestModel = $this->stock->request(
            $request->user(),
            $this->branchId(),
            StockLocationType::from($data['destination']),
            $data['lines'],
            $data['notes'] ?? null,
            $data['idempotency_key'] ?? null,
        );

        return response()->json(['data' => $requestModel], 201);
    }

    public function approve(Request $request, StockRequest $stockRequest): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::StockIssue), 403);
        abort_unless($stockRequest->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.approved_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->stock->approve($stockRequest->load('lines'), $request->user(), $data['lines'])]);
    }

    public function reject(Request $request, StockRequest $stockRequest): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::StockIssue), 403);
        abort_unless($stockRequest->branch_id === $this->branchId(), 404);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $this->stock->reject($stockRequest, $request->user(), $data['reason'])]);
    }

    public function issue(Request $request, StockRequest $stockRequest): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::StockIssue), 403);
        abort_unless($stockRequest->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'lines' => ['sometimes', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.issued_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->stock->issue($stockRequest->load('lines'), $request->user(), $data['lines'] ?? [])]);
    }

    public function confirm(Request $request, StockRequest $stockRequest): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::StockConfirm), 403);
        abort_unless($stockRequest->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->stock->confirm($stockRequest->load('lines'), $request->user(), $data['lines'])]);
    }

    public function redeem(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BarRedeem), 403);

        $data = $request->validate([
            'bono_type_id' => ['required', 'integer'],
            'inventory_item_id' => ['required', 'integer'],
            'menu_item_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'waiter_id' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $redemption = $this->stock->redeem(
            $request->user(),
            $this->branchId(),
            (int) $data['bono_type_id'],
            (int) $data['inventory_item_id'],
            (string) $data['quantity'],
            $data['idempotency_key'],
            isset($data['waiter_id']) ? (int) $data['waiter_id'] : null,
            isset($data['menu_item_id']) ? (int) $data['menu_item_id'] : null,
        );

        return response()->json(['data' => $redemption], 201);
    }

    public function waste(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BarClose)
            || $request->user()->hasPermission(Permission::KitchenReconcile),
            403,
        );

        $data = $request->validate([
            'location' => ['required', 'in:bar,kitchen'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $this->stock->waste($request->user(), $this->branchId(), StockLocationType::from($data['location']), $data['lines']);

        return response()->json(['message' => 'Waste recorded.']);
    }

    public function count(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BarClose)
            || $request->user()->hasPermission(Permission::KitchenReconcile),
            403,
        );

        $data = $request->validate([
            'location' => ['required', 'in:bar,kitchen'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.physical_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $count = $this->stock->count(
            $request->user(),
            $this->branchId(),
            StockLocationType::from($data['location']),
            $data['lines'],
            $data['notes'] ?? null,
        );

        return response()->json(['data' => $count], 201);
    }

    public function kitchenSales(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::KitchenReconcile), 403);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.source' => ['sometimes', 'in:pos,manual'],
        ]);

        $this->stock->recordKitchenSales($request->user(), $this->branchId(), $data['lines']);

        return response()->json(['message' => 'Kitchen sales summary recorded. It is not added to POS revenue.'], 201);
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
