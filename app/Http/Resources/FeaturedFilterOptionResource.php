<?php

namespace App\Http\Resources;

use App\Data\FilterOption;
use Illuminate\Http\Request;

/**
 * A filter option that can be highlighted in the UI (platforms, product types, genres).
 *
 * @mixin FilterOption
 */
class FeaturedFilterOptionResource extends FilterOptionResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /**
             * Icon key for the frontend's icon set, or null when there is none.
             *
             * @example steam
             */
            'icon' => $this->icon,
            /** Whether to highlight this option, e.g. as a quick filter. */
            'is_featured' => $this->isFeatured,
        ];
    }
}
