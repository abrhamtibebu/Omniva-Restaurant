<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Modifier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    use ResolvesBranch;

    public function __construct(private AuditLogger $audit) {}

    public function categories(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission(Permission::MenuView), 403);

        $categories = MenuCategory::query()
            ->where('branch_id', $this->branchId())
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return MenuCategoryResource::collection($categories);
    }

    public function storeCategory(Request $request): MenuCategoryResource
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $category = MenuCategory::query()->create(['branch_id' => $this->branchId(), ...$data]);

        return new MenuCategoryResource($category);
    }

    public function updateCategory(Request $request, MenuCategory $category): MenuCategoryResource
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);
        abort_unless($category->branch_id === $this->branchId(), 404);

        $category->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return new MenuCategoryResource($category);
    }

    public function destroyCategory(Request $request, MenuCategory $category): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);
        abort_unless($category->branch_id === $this->branchId(), 404);

        $category->update(['active' => false]);

        return response()->json(['message' => 'Category deactivated.']);
    }

    public function items(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission(Permission::MenuView), 403);

        $items = MenuItem::query()
            ->with(['category', 'variants', 'modifiers'])
            ->whereHas('category', fn ($q) => $q->where('branch_id', $this->branchId()))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('available_only'), fn ($q) => $q->where('available', true)->where('active', true))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 100));

        return MenuItemResource::collection($items);
    }

    public function storeItem(Request $request): MenuItemResource
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);

        $data = $request->validate([
            'category_id' => ['required', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'available' => ['sometimes', 'boolean'],
            'requires_kitchen' => ['sometimes', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0'],
            'modifier_ids' => ['sometimes', 'array'],
            'modifier_ids.*' => ['integer', 'exists:modifiers,id'],
        ]);

        $category = MenuCategory::query()->findOrFail($data['category_id']);
        abort_unless($category->branch_id === $this->branchId(), 422, 'Category does not belong to this branch.');

        $item = MenuItem::query()->create(collect($data)->except('modifier_ids')->all());

        if (! empty($data['modifier_ids'])) {
            $item->modifiers()->sync($data['modifier_ids']);
        }

        return new MenuItemResource($item->load(['category', 'variants', 'modifiers']));
    }

    public function updateItem(Request $request, MenuItem $item): MenuItemResource
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);
        abort_unless($item->category->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'category_id' => ['sometimes', 'exists:menu_categories,id'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'available' => ['sometimes', 'boolean'],
            'requires_kitchen' => ['sometimes', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0'],
            'modifier_ids' => ['sometimes', 'array'],
            'modifier_ids.*' => ['integer', 'exists:modifiers,id'],
        ]);

        $oldPrice = (string) $item->base_price;
        $item->update(collect($data)->except('modifier_ids')->all());

        if (array_key_exists('modifier_ids', $data)) {
            $item->modifiers()->sync($data['modifier_ids']);
        }

        if (isset($data['base_price']) && (string) $data['base_price'] !== $oldPrice) {
            $this->audit->record('menu.price_changed', $item, ['base_price' => $oldPrice], ['base_price' => (string) $item->base_price], $request->user());
        }

        return new MenuItemResource($item->fresh(['category', 'variants', 'modifiers']));
    }

    public function storeVariant(Request $request, MenuItem $item): MenuItemResource
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $item->variants()->create($data);

        return new MenuItemResource($item->fresh(['category', 'variants', 'modifiers']));
    }

    public function updateVariant(Request $request, MenuItemVariant $variant): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);

        $variant->update($request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $variant]);
    }

    public function modifiers(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::MenuView), 403);

        $modifiers = Modifier::query()
            ->where('branch_id', $this->branchId())
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $modifiers]);
    }

    public function storeModifier(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);

        $modifier = Modifier::query()->create([
            'branch_id' => $this->branchId(),
            ...$request->validate([
                'name' => ['required', 'string', 'max:80'],
                'price' => ['required', 'numeric', 'min:0'],
                'active' => ['sometimes', 'boolean'],
            ]),
        ]);

        return response()->json(['data' => $modifier], 201);
    }

    public function updateModifier(Request $request, Modifier $modifier): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::MenuManage), 403);
        abort_unless($modifier->branch_id === $this->branchId(), 404);

        $modifier->update($request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $modifier]);
    }
}
