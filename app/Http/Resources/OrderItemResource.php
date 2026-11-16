<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line of an order, with the product details and prices as they were at checkout.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->order->currency;

        return [
            /**
             * ID of the product bought, or null if it has since been removed from the catalog.
             *
             * @example 12
             */
            'product_id' => $this->product_id,
            /** Product title at checkout. */
            'title' => $this->title,
            /**
             * Platform name at checkout.
             *
             * @example Steam
             */
            'platform' => $this->platform_label,
            /**
             * Region name at checkout.
             *
             * @example Global
             */
            'region' => $this->region_label,
            /** Price of one key at checkout. */
            'unit_price' => $this->money($this->unit_price, $currency),
            /** Cashback earned per key. */
            'unit_cashback' => $this->money($this->unit_cashback, $currency),
            /**
             * Number of keys.
             *
             * @example 1
             */
            'quantity' => $this->quantity,
            /** `unit_price` × `quantity`. */
            'line_total' => $this->money($this->line_total, $currency),
        ];
    }
}
