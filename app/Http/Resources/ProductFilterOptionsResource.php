<?php

namespace App\Http\Resources;

use App\Data\ProductFilterOptions;
use App\Http\Resources\Concerns\FormatsMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Filter options for the product listing, each with the number of active products that match it.
 *
 * @mixin ProductFilterOptions
 */
class ProductFilterOptionsResource extends JsonResource
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
            /** Every platform in display order, including those with no products. */
            'platforms' => FeaturedFilterOptionResource::collection($this->platforms),
            /** Every region in display order, including those with no products. */
            'regions' => FilterOptionResource::collection($this->regions),
            /** Every country in display order, counting active products whose region includes it. */
            'countries' => FilterOptionResource::collection($this->countries),
            /** Every product type in display order, including those with no products. */
            'types' => FeaturedFilterOptionResource::collection($this->types),
            /** Every genre in display order, including those with no products. */
            'genres' => FeaturedFilterOptionResource::collection($this->genres),
            /** Every operating system in display order, including those with no products. */
            'operating_systems' => FilterOptionResource::collection($this->operatingSystems),
            /** Price range of active products. Both bounds are null when there are no active products. */
            'price' => [
                /** Lowest price among active products. */
                'min' => $this->minPrice === null ? null : $this->money($this->minPrice, $this->currency),
                /** Highest price among active products. */
                'max' => $this->maxPrice === null ? null : $this->money($this->maxPrice, $this->currency),
            ],
            /**
             * ISO 4217 currency code for the price range.
             *
             * @example EUR
             */
            'currency' => $this->currency,
        ];
    }
}
