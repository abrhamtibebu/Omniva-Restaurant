<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ResolvesBranch;

    public function sales(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReportsView), 403);

        $query = $this->completedOrders($request);
        $count = (clone $query)->count();
        $total = (string) (clone $query)->sum('total');

        return response()->json([
            'data' => [
                'total_sales' => Money::of($total),
                'number_of_orders' => $count,
                'average_order_value' => $count ? Money::of(bcdiv($total, (string) $count, 2)) : '0.00',
                'discounts' => Money::of((string) (clone $query)->sum('discount')),
                'tax' => Money::of((string) (clone $query)->sum('tax')),
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReportsView), 403);

        $items = OrderItem::query()
            ->select('item_name_snapshot as menu_item', DB::raw('SUM(quantity) as quantity_sold'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('order', fn ($q) => $this->applyOrderFilters($q, $request))
            ->groupBy('item_name_snapshot')
            ->orderByDesc('revenue')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function payments(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReportsView), 403);

        $totals = Payment::query()
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->where('status', PaymentStatus::Completed)
            ->whereHas('order', fn ($q) => $this->applyOrderFilters($q, $request))
            ->groupBy('payment_method')
            ->get();

        return response()->json(['data' => $totals]);
    }

    public function expenses(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReportsView), 403);

        $from = $request->date('from', now()->startOfMonth());
        $to = $request->date('to', now());

        $rows = Expense::query()
            ->select('category', DB::raw('SUM(amount) as total'))
            ->where('branch_id', $this->branchId())
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('category')
            ->get();

        return response()->json([
            'data' => [
                'by_category' => $rows,
                'total_expenses' => Money::of((string) $rows->sum('total')),
            ],
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReportsView), 403);

        $items = InventoryItem::query()
            ->where('branch_id', $this->branchId())
            ->orderBy('name')
            ->get();

        $movements = InventoryTransaction::query()
            ->with('inventoryItem:id,name')
            ->whereHas('inventoryItem', fn ($q) => $q->where('branch_id', $this->branchId()))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'data' => [
                'current_quantities' => $items,
                'low_stock' => $items->filter->isLowStock()->values(),
                'movements' => $movements,
            ],
        ]);
    }

    private function completedOrders(Request $request)
    {
        return Order::query()
            ->where('status', OrderStatus::Completed)
            ->tap(fn ($q) => $this->applyOrderFilters($q, $request));
    }

    private function applyOrderFilters($query, Request $request)
    {
        $query->where('branch_id', $request->integer('branch_id') ?: $this->branchId())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('completed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('completed_at', '<=', $request->date('to')))
            ->when($request->filled('waiter_id'), fn ($q) => $q->where('waiter_id', $request->integer('waiter_id')))
            ->when($request->filled('payment_method'), function ($q) use ($request) {
                $q->whereHas('payments', fn ($p) => $p
                    ->where('payment_method', $request->string('payment_method'))
                    ->where('status', PaymentStatus::Completed));
            });

        return $query;
    }
}
