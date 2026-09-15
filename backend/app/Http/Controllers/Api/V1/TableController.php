<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission(Permission::TablesView), 403);

        $tables = RestaurantTable::query()
            ->with(['assignedWaiter', 'currentOrder'])
            ->where('branch_id', $this->branchId())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->get();

        return RestaurantTableResource::collection($tables);
    }

    public function store(Request $request): RestaurantTableResource
    {
        abort_unless($request->user()->hasPermission(Permission::TablesManage), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('restaurant_tables', 'name')->where('branch_id', $this->branchId())],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $table = RestaurantTable::query()->create([
            ...$data,
            'branch_id' => $this->branchId(),
            'status' => TableStatus::Available,
        ]);

        return new RestaurantTableResource($table->load(['assignedWaiter', 'currentOrder']));
    }

    public function update(Request $request, RestaurantTable $table): RestaurantTableResource
    {
        abort_unless($request->user()->hasPermission(Permission::TablesManage), 403);
        abort_unless($table->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:40', Rule::unique('restaurant_tables', 'name')->where('branch_id', $this->branchId())->ignore($table->id)],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'active' => ['sometimes', 'boolean'],
            'assigned_waiter_id' => ['nullable', 'exists:users,id'],
        ]);

        $table->update($data);

        return new RestaurantTableResource($table->fresh(['assignedWaiter', 'currentOrder']));
    }

    public function updateStatus(Request $request, RestaurantTable $table): RestaurantTableResource
    {
        abort_unless($request->user()->hasPermission(Permission::TablesUpdateStatus), 403);
        abort_unless($table->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TableStatus::class)],
        ]);

        $table->update(['status' => $data['status']]);

        return new RestaurantTableResource($table->fresh(['assignedWaiter', 'currentOrder']));
    }

    public function destroy(Request $request, RestaurantTable $table): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::TablesManage), 403);
        abort_unless($table->branch_id === $this->branchId(), 404);

        if ($table->current_order_id) {
            return response()->json(['message' => 'Cannot delete a table with an open order.'], 422);
        }

        $table->delete();

        return response()->json(['message' => 'Table deleted.']);
    }
}
