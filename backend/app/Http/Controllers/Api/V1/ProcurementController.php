<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\PurchasePaymentStatus;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\SequenceService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProcurementController extends Controller
{
    use ResolvesBranch;

    public function suppliers(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::SuppliersView), 403);

        $suppliers = Supplier::query()
            ->where('branch_id', $this->branchId())
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json($suppliers);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::SuppliersManage), 403);

        $supplier = Supplier::query()->create([
            'branch_id' => $this->branchId(),
            ...$request->validate([
                'name' => ['required', 'string', 'max:160'],
                'contact_person' => ['nullable', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email'],
                'address' => ['nullable', 'string'],
                'active' => ['sometimes', 'boolean'],
            ]),
        ]);

        return response()->json(['data' => $supplier], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::SuppliersManage), 403);
        abort_unless($supplier->branch_id === $this->branchId(), 404);

        $supplier->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $supplier]);
    }

    public function showSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::SuppliersView), 403);
        abort_unless($supplier->branch_id === $this->branchId(), 404);

        return response()->json([
            'data' => $supplier->load(['purchases' => fn ($q) => $q->latest()->limit(20)]),
        ]);
    }

    public function purchases(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::PurchasesView), 403);

        $purchases = Purchase::query()
            ->with('supplier')
            ->where('branch_id', $this->branchId())
            ->latest('purchase_date')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json($purchases);
    }

    public function storePurchase(Request $request, SequenceService $sequences): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::PurchasesManage), 403);

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'payment_status' => ['sometimes', Rule::enum(PurchasePaymentStatus::class)],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($data, $request, $sequences) {
            $subtotal = '0.00';
            foreach ($data['items'] as $line) {
                $subtotal = Money::add($subtotal, Money::multiply((string) $line['unit_cost'], (string) $line['quantity']));
            }

            $purchase = Purchase::query()->create([
                'branch_id' => $this->branchId(),
                'supplier_id' => $data['supplier_id'],
                'purchase_number' => $sequences->nextPurchaseNumber($this->branchId()),
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'payment_status' => $data['payment_status'] ?? PurchasePaymentStatus::Unpaid->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $lineSubtotal = Money::multiply((string) $line['unit_cost'], (string) $line['quantity']);
                $purchase->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            return $purchase;
        });

        return response()->json(['data' => $purchase->load('items.inventoryItem', 'supplier')], 201);
    }

    public function showPurchase(Request $request, Purchase $purchase): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::PurchasesView), 403);
        abort_unless($purchase->branch_id === $this->branchId(), 404);

        return response()->json(['data' => $purchase->load('items.inventoryItem', 'supplier', 'creator')]);
    }

    public function receivePurchase(Request $request, Purchase $purchase, InventoryService $inventory): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::PurchasesManage), 403);
        abort_unless($purchase->branch_id === $this->branchId(), 404);

        DB::transaction(fn () => $inventory->receivePurchase($purchase, $request->user()));

        return response()->json(['data' => $purchase->fresh(['items.inventoryItem', 'supplier'])]);
    }
}
