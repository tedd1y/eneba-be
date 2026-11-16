<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PayOrder
{
    /**
     * Take a mock payment for the order, which always succeeds.
     *
     * Paying an order that is already paid returns it unchanged, so a retried request never charges twice.
     *
     * @throws ConflictHttpException when the order is in a state that cannot be paid, such as cancelled.
     */
    public function handle(Order $order): Order
    {
        $paidOrder = DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->status->isPaid()) {
                return $lockedOrder;
            }

            if (! $lockedOrder->status->canBePaid()) {
                throw new ConflictHttpException("Orders that are {$lockedOrder->status->value} cannot be paid.");
            }

            $lockedOrder->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            return $lockedOrder;
        });

        return $paidOrder->load('items');
    }
}
