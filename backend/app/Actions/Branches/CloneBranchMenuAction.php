<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\MenuCategory;
use App\Models\Modifier;
use Illuminate\Support\Facades\DB;

class CloneBranchMenuAction
{
    /**
     * Copy categories, items, variants, and modifiers onto a new location.
     * Tables and inventory stay empty — those are physical to the site.
     */
    public function execute(int $fromBranchId, int $toBranchId): void
    {
        DB::transaction(function () use ($fromBranchId, $toBranchId) {
            $modifierMap = [];

            foreach (Modifier::query()->where('branch_id', $fromBranchId)->get() as $modifier) {
                $copy = $modifier->replicate();
                $copy->branch_id = $toBranchId;
                $copy->save();
                $modifierMap[$modifier->id] = $copy->id;
            }

            $categories = MenuCategory::query()
                ->where('branch_id', $fromBranchId)
                ->with(['items.variants', 'items.modifiers'])
                ->orderBy('sort_order')
                ->get();

            foreach ($categories as $category) {
                $newCategory = $category->replicate();
                $newCategory->branch_id = $toBranchId;
                $newCategory->save();

                foreach ($category->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->category_id = $newCategory->id;
                    $newItem->save();

                    foreach ($item->variants as $variant) {
                        $newVariant = $variant->replicate();
                        $newVariant->menu_item_id = $newItem->id;
                        $newVariant->save();
                    }

                    $modifierIds = $item->modifiers
                        ->map(fn (Modifier $modifier): ?int => $modifierMap[$modifier->id] ?? null)
                        ->filter()
                        ->values()
                        ->all();

                    if ($modifierIds !== []) {
                        $newItem->modifiers()->sync($modifierIds);
                    }
                }
            }
        });
    }
}
