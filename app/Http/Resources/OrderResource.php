<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Public order reference, used to fetch and pay the order.
             *
             * @example O-7KD2XQ9M
             */
            'number' => $this->number,
            /**
             * @format email
             */
            'email' => $this->email,
            /** Where the order is in its lifecycle. */
            'status' => [
                'value' => $this->status,
                /** @example Pending */
                'label' => $this->status->label(),
            ],
            /** Sum of all line totals. */
            'subtotal' => $this->money($this->subtotal, $this->currency),
            /** Total cashback earned on the order. */
            'cashback' => $this->money($this->cashback, $this->currency),
            /**
             * ISO 4217 currency code for all amounts.
             *
             * @example EUR
             */
            'currency' => $this->currency,
            /** @var OrderItemResource[] */
            'items' => OrderItemResource::collection($this->items),
            /**
             * When payment was taken, or null while unpaid.
             *
             * @format date-time
             */
            'paid_at' => $this->paid_at?->toIso8601String(),
            /**
             * @format date-time
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * @format date-time
             */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
