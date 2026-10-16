<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Kitchen\UpdateKitchenStatusAction;
use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    use ResolvesBranch;

    public function orders(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission(Permission::KitchenView), 403);

        $orders = Order::query()
            ->with(['table', 'waiter', 'items.modifiers'])
            ->where('branch_id', $this->branchId())
            ->whereIn('status', [
                OrderStatus::Submitted->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ])
            ->whereHas('items', fn ($q) => $q->where('submitted_to_kitchen', true))
            ->orderBy('opened_at')
            ->get();

        return OrderResource::collection($orders);
    }

    public function updateItemStatus(Request $request, OrderItem $item, UpdateKitchenStatusAction $action): OrderItemResource
    {
        abort_unless($request->user()->hasPermission(Permission::KitchenUpdateStatus), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(KitchenStatus::class)],
        ]);

        return new OrderItemResource(
            $action->execute($item, KitchenStatus::from($data['status']))->load('modifiers'),
        );
    }
}
