<?php

namespace App\Http\Resources;

use App\Data\FilterOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single filter option and how many active products match it.
 *
 * @mixin FilterOption
 */
class FilterOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Value to send back in the matching `/v1/products` query parameter.
             *
             * @example steam
             */
            'value' => $this->value,
            /**
             * Human-readable name.
             *
             * @example Steam
             */
            'label' => $this->label,
            /**
             * Number of active products matching this option.
             *
             * @example 12
             */
            'count' => $this->count,
        ];
    }
}
