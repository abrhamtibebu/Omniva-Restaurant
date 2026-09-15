<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InventoryTransactionType;
use App\Enums\InventoryUnit;
use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\RecipeItem;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::InventoryView), 403);

        $items = InventoryItem::query()
            ->where('branch_id', $this->branchId())
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'minimum_stock'))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 30), 100));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::InventoryManage), 403);

        $item = InventoryItem::query()->create([
            'branch_id' => $this->branchId(),
            ...$request->validate([
                'name' => ['required', 'string', 'max:160'],
                'sku' => ['nullable', 'string', 'max:80'],
                'unit' => ['required', Rule::enum(InventoryUnit::class)],
                'quantity_on_hand' => ['sometimes', 'numeric', 'min:0'],
                'minimum_stock' => ['sometimes', 'numeric', 'min:0'],
                'average_cost' => ['nullable', 'numeric', 'min:0'],
                'active' => ['sometimes', 'boolean'],
            ]),
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, InventoryItem $inventory): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::InventoryManage), 403);
        abort_unless($inventory->branch_id === $this->branchId(), 404);

        $inventory->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80'],
            'unit' => ['sometimes', Rule::enum(InventoryUnit::class)],
            'minimum_stock' => ['sometimes', 'numeric', 'min:0'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $inventory]);
    }

    public function adjust(Request $request, InventoryItem $inventory, InventoryService $inventoryService, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::InventoryAdjust), 403);
        abort_unless($inventory->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'type' => ['required', Rule::in([
                InventoryTransactionType::AdjustmentIn->value,
                InventoryTransactionType::AdjustmentOut->value,
                InventoryTransactionType::Waste->value,
            ])],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        $txn = $inventoryService->move(
            item: $inventory,
            type: InventoryTransactionType::from($data['type']),
            quantity: (string) $data['quantity'],
            user: $request->user(),
            notes: $data['notes'] ?? null,
        );

        $audit->record('inventory.adjusted', $inventory, null, $data, $request->user());

        return response()->json(['data' => $txn->fresh('inventoryItem')]);
    }

    public function recipes(Request $request, InventoryItem $inventory): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::RecipesView), 403);

        return response()->json(['data' => $inventory->transactions()->latest()->limit(50)->get()]);
    }

    public function storeRecipe(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::RecipesManage), 403);

        $data = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_required' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::enum(InventoryUnit::class)],
        ]);

        $recipe = RecipeItem::query()->updateOrCreate(
            [
                'menu_item_id' => $data['menu_item_id'],
                'inventory_item_id' => $data['inventory_item_id'],
            ],
            [
                'quantity_required' => $data['quantity_required'],
                'unit' => $data['unit'],
            ],
        );

        return response()->json(['data' => $recipe->load('inventoryItem')], 201);
    }
}
