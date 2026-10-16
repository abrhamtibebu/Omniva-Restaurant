<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\AddOrderItemAction;
use App\Actions\Orders\ApplyDiscountAction;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\RemoveOrderItemAction;
use App\Actions\Orders\ServeOrderAction;
use App\Actions\Orders\SubmitOrderAction;
use App\Actions\Orders\UpdateOrderItemAction;
use App\Actions\Payments\RecordPaymentAction;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with(['table', 'waiter', 'payments'])
            ->where('branch_id', $this->branchId())
            ->when(
                ! $request->user()->hasPermission(Permission::OrdersView),
                fn ($q) => $q->where('waiter_id', $request->user()->id),
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('table_id'), fn ($q) => $q->where('table_id', $request->integer('table_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('search'), fn ($q) => $q->where('order_number', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(min($request->integer('per_page', 20), 100));

        return OrderResource::collection($orders);
    }

    public function store(Request $request, CreateOrderAction $action): OrderResource
    {
        $this->authorize('create', Order::class);

        $data = $request->validate([
            'table_id' => ['nullable', 'exists:restaurant_tables,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'type' => ['required', Rule::enum(OrderType::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $order = $action->execute(
            $request->user(),
            Branch::query()->findOrFail($this->branchId()),
            $data,
        );

        return new OrderResource($order->load(['table', 'waiter', 'items.modifiers', 'payments']));
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load([
            'table', 'waiter', 'customer', 'items.modifiers', 'payments.cashier',
        ]));
    }

    public function addItem(Request $request, Order $order, AddOrderItemAction $action): OrderItemResource
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'menu_item_variant_id' => ['nullable', 'exists:menu_item_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'modifier_ids' => ['sometimes', 'array'],
            'modifier_ids.*' => ['integer', 'exists:modifiers,id'],
        ]);

        return new OrderItemResource($action->execute($order, $data));
    }

    public function updateItem(Request $request, Order $order, OrderItem $item, UpdateOrderItemAction $action): OrderItemResource
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return new OrderItemResource($action->execute($order, $item, $data, $request->user()));
    }

    public function removeItem(Request $request, Order $order, OrderItem $item, RemoveOrderItemAction $action): JsonResponse
    {
        $this->authorize('update', $order);
        $action->execute($order, $item, $request->user());

        return response()->json(['message' => 'Item removed.']);
    }

    public function submit(Request $request, Order $order, SubmitOrderAction $action): OrderResource
    {
        $this->authorize('submit', $order);

        return new OrderResource($action->execute($order)->load(['table', 'waiter', 'items.modifiers', 'payments']));
    }

    public function serve(Request $request, Order $order, ServeOrderAction $action): OrderResource
    {
        $this->authorize('serve', $order);

        return new OrderResource($action->execute($order)->load(['table', 'waiter', 'items.modifiers', 'payments']));
    }

    public function cancel(Request $request, Order $order, CancelOrderAction $action): OrderResource
    {
        $this->authorize('cancel', $order);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return new OrderResource($action->execute($order, $request->user(), $data['reason'] ?? null));
    }

    public function discount(Request $request, Order $order, ApplyDiscountAction $action): OrderResource
    {
        $this->authorize('discount', $order);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        return new OrderResource(
            $action->execute($order, (string) $data['amount'], $request->user())
                ->load(['table', 'waiter', 'items.modifiers', 'payments']),
        );
    }

    public function pay(Request $request, Order $order, RecordPaymentAction $action): PaymentResource
    {
        $this->authorize('pay', $order);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        return new PaymentResource($action->execute($order, $request->user(), $data)->load('cashier'));
    }
}
