<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Full product representation for the product page: everything in `ProductResource` plus long-form content.
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
        ];
    }
}
