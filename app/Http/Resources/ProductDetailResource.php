<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Full product representation for the product page: everything in `ProductResource` plus description, genres and operating systems.
 *
 * @mixin Product
 */
class ProductDetailResource extends ProductResource
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
             * Long-form product description as plain text. Paragraphs are separated by blank lines.
             */
            'description' => $this->description,
            /** Genres the product belongs to, in display order. */
            'genres' => LookupResource::collection($this->genres),
            /** Operating systems the product runs on, in display order. Empty for console products. */
            'operating_systems' => LookupResource::collection($this->operatingSystems),
        ];
    }
}
