<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class OrderController extends Controller
{
    /**
     * Place a guest order.
     *
     * Creates a pending order priced from the catalog. Pay it with `POST /v1/orders/{order}/pay`, using the returned `uuid`.
     * Limited to 10 requests per minute per client.
     *
     * @throws ThrottleRequestsException
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): OrderResource
    {
        /** @status 201 */
        return new OrderResource($createOrder->handle($request->validated('email'), $request->validated('items')));
    }

    /**
     * Display an order by its UUID.
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('items'));
    }
}
