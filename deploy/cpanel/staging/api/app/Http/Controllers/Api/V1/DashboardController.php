<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResolvesBranch;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::DashboardView), 403);

        $branchId = $this->branchId();
        $from = $request->filled('from')
            ? $request->date('from')->startOfDay()
            : now()->startOfDay();
        $to = $request->filled('to')
            ? $request->date('to')->endOfDay()
            : now()->endOfDay();

        $sales = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$from, $to]);

        $gross = (string) (clone $sales)->sum('total');
        $count = (clone $sales)->count();
        $discounts = (string) (clone $sales)->sum('discount');
        $tax = (string) (clone $sales)->sum('tax');
        $aov = $count > 0 ? Money::of(bcdiv($gross, (string) $count, 2)) : '0.00';

        $expenses = (string) Expense::query()
            ->where('branch_id', $branchId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $openStatuses = [
            OrderStatus::Draft->value,
            OrderStatus::Submitted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
        ];

        $operational = [
            'open_orders' => Order::query()->where('branch_id', $branchId)->whereIn('status', $openStatuses)->count(),
            'preparing_orders' => Order::query()->where('branch_id', $branchId)->where('status', OrderStatus::Preparing)->count(),
            'ready_orders' => Order::query()->where('branch_id', $branchId)->where('status', OrderStatus::Ready)->count(),
            'occupied_tables' => RestaurantTable::query()->where('branch_id', $branchId)->where('status', TableStatus::Occupied)->count(),
            'available_tables' => RestaurantTable::query()->where('branch_id', $branchId)->where('status', TableStatus::Available)->where('active', true)->count(),
            'low_stock_items' => InventoryItem::query()
                ->where('branch_id', $branchId)
                ->whereColumn('quantity_on_hand', '<=', 'minimum_stock')
                ->where('active', true)
                ->get(['id', 'name', 'quantity_on_hand', 'minimum_stock', 'unit']),
        ];

        $topItems = OrderItem::query()
            ->select('item_name_snapshot', DB::raw('SUM(quantity) as quantity_sold'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('order', function ($q) use ($branchId, $from, $to) {
                $q->where('branch_id', $branchId)
                    ->where('status', OrderStatus::Completed)
                    ->whereBetween('completed_at', [$from, $to]);
            })
            ->groupBy('item_name_snapshot')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        $byMethod = Payment::query()
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('order', fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('payment_method')
            ->get();

        $byWaiter = Order::query()
            ->select('waiter_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total) as sales'))
            ->with('waiter:id,name')
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$from, $to])
            ->groupBy('waiter_id')
            ->get();

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
                'today' => [
                    'gross_sales' => Money::of($gross),
                    'completed_orders' => $count,
                    'average_order_value' => $aov,
                    'discounts' => Money::of($discounts),
                    'tax' => Money::of($tax),
                    'expenses' => Money::of($expenses),
                    'estimated_net' => Money::subtract(Money::of($gross), Money::of($expenses)),
                ],
                'operational' => $operational,
                'top_selling_items' => $topItems,
                'sales_by_payment_method' => $byMethod,
                'sales_by_waiter' => $byWaiter,
            ],
        ]);
    }
}
