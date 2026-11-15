<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'id' => $this->id,
            'title' => $this->title,
            /**
             * URL-friendly unique identifier, used to fetch a single product.
             *
             * @example baldurs-gate-3-steam-global
             */
            'slug' => $this->slug,
            /** Platform the key activates on. */
            'platform' => [
                'value' => $this->platform->value,
                'label' => $this->platform->label(),
            ],
            /** Region the key can be activated in. */
            'region' => [
                'value' => $this->region->value,
                'label' => $this->region->label(),
            ],
            /** Kind of product. */
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            /** Current selling price. */
            'price' => $this->money($this->price, $this->currency),
            /** Price before discount, or null when the product is not discounted. */
            'original_price' => $this->original_price === null ? null : $this->money($this->original_price, $this->currency),
            /** Cashback earned on purchase, or null when none is offered. */
            'cashback' => $this->cashback === null ? null : $this->money($this->cashback, $this->currency),
            /**
             * Discount from `original_price` to `price`, as a whole percentage (0 when not discounted).
             *
             * @example 28
             */
            'discount_percent' => $this->discountPercent(),
            /**
             * ISO 4217 currency code for all amounts.
             *
             * @example EUR
             */
            'currency' => $this->currency,
            /**
             * @format uri
             */
            'image_url' => $this->image_url,
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
