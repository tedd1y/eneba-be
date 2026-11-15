<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
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
            'slug' => $this->slug,
            'platform' => [
                'value' => $this->platform->value,
                'label' => $this->platform->label(),
            ],
            'region' => [
                'value' => $this->region->value,
                'label' => $this->region->label(),
            ],
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'price' => $this->money($this->price),
            'original_price' => $this->money($this->original_price),
            'cashback' => $this->money($this->cashback),
            'discount_percent' => $this->discountPercent(),
            'currency' => $this->currency,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Represent an amount in cents as both raw cents and a formatted string.
     *
     * @return array{amount: int, formatted: string}|null
     */
    private function money(?int $cents): ?array
    {
        if ($cents === null) {
            return null;
        }

        return [
            'amount' => $cents,
            'formatted' => Number::currency($cents / 100, in: $this->currency, locale: 'en'),
        ];
    }
}
