<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function show(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurantId();
        $subscription = $this->subscriptions->sync($restaurantId);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurantId)
            ->latest()
            ->get();

        $payload = [
            'subscription' => $this->subscriptions->present($subscription),
            'invoices' => $invoices,
        ];

        if ($request->user()->is_platform) {
            $payload['platform_invoices'] = Invoice::query()
                ->with('restaurant:id,name')
                ->where('status', 'open')
                ->latest()
                ->get();
        }

        return response()->json(['data' => $payload]);
    }

    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()->is_platform, 403, 'Only a platform operator can record a payment.');

        $subscription = $this->subscriptions->recordPayment($invoice, $request->user());

        return response()->json([
            'data' => $this->subscriptions->present($subscription),
        ]);
    }
}
