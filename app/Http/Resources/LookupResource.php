<?php

namespace App\Http\Resources;

use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lookup record (platform, region, product type, genre, operating system) as a value/label pair.
 *
 * Every lookup model has the same `slug` and `name` columns; `Genre` stands in for all of them here.
 *
 * @mixin Genre
 */
class LookupResource extends JsonResource
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
             * Slug, the same value the `/v1/products` filters accept.
             *
             * @example steam
             */
            'value' => $this->slug,
            /**
             * Human-readable name.
             *
             * @example Steam
             */
            'label' => $this->name,
        ];
    }
}
