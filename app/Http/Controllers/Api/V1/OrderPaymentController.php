<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PayOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class OrderPaymentController extends Controller
{
    /**
     * Pay an order.
     *
     * Takes a mock payment that always succeeds. Paying an already paid order returns it unchanged, so retries are safe.
     * Returns 409 when the order cannot be paid, e.g. because it was cancelled. Limited to 10 requests per minute per client.
     *
     * @throws ThrottleRequestsException
     */
    public function store(Order $order, PayOrder $payOrder): OrderResource
    {
        return new OrderResource($payOrder->handle($order));
    }
}
