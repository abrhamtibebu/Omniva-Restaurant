<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\CancelPaymentAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function cancel(Request $request, Payment $payment, CancelPaymentAction $action): PaymentResource
    {
        abort_unless($request->user()->hasPermission(Permission::PaymentsCancel), 403);

        return new PaymentResource($action->execute($payment, $request->user(), 'cancelled'));
    }

    public function refund(Request $request, Payment $payment, CancelPaymentAction $action): PaymentResource
    {
        abort_unless($request->user()->hasPermission(Permission::PaymentsRefund), 403);

        return new PaymentResource($action->execute($payment, $request->user(), 'refunded'));
    }
}
