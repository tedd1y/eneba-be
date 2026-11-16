<?php

namespace App\Http\Resources;

use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A genre: the same value/label pair as `LookupResource`, plus how to present it.
 *
 * Kept separate from `LookupResource` (rather than extending it) so the OpenAPI generator documents it as its own schema.
 *
 * @mixin Genre
 */
class GenreResource extends JsonResource
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
             * Slug, the value the `genre[]` filter of `/v1/products` accepts.
             *
             * @example action
             */
            'value' => $this->slug,
            /**
             * Human-readable name.
             *
             * @example Action
             */
            'label' => $this->name,
            /**
             * Number of active products in this genre, as on `/v1/products/filters`.
             *
             * @example 12
             */
            'count' => (int) $this->products_count,
            /**
             * Icon key for the frontend's icon set, or null when there is none.
             *
             * @example sword
             */
            'icon' => $this->icon,
            /** Whether to highlight this genre, e.g. as a quick filter. */
            'is_featured' => $this->is_featured,
        ];
    }
}
