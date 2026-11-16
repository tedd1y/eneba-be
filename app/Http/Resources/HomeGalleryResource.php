<?php

namespace App\Http\Resources;

use App\Models\HomeGallery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HomeGallery
 */
class HomeGalleryResource extends JsonResource
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
            /**
             * Gallery heading.
             *
             * @example Top games
             */
            'label' => $this->label,
            /**
             * Storefront "See all" link, relative to the site root. Its query string uses the `GET /v1/products` filters.
             *
             * @example /store/all?type[]=game&sort=-price
             */
            'href' => $this->href,
            /**
             * Number of products to show in the gallery; pass it as `per_page` when loading them.
             *
             * @example 10
             */
            'limit' => $this->limit,
        ];
    }
}
