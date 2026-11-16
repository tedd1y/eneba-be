<?php

namespace App\Actions;

use App\Data\FilterOption;
use App\Data\ProductFilterOptions;
use App\Models\Country;
use App\Models\Genre;
use App\Models\OperatingSystem;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GetProductFilterOptions
{
    /**
     * Collect every filter option in display order with the number of active products matching it,
     * plus the price range of active products. Runs a fixed number of queries regardless of data size.
     */
    public function handle(): ProductFilterOptions
    {
        $price = Product::query()->active()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->toBase()
            ->first();

        return new ProductFilterOptions(
            platforms: $this->optionsWithProductCounts(Platform::class, featured: true),
            regions: $this->optionsWithProductCounts(Region::class),
            countries: $this->countryOptions(),
            types: $this->optionsWithProductCounts(ProductType::class, featured: true),
            genres: $this->optionsWithProductCounts(Genre::class, featured: true),
            operatingSystems: $this->optionsWithProductCounts(OperatingSystem::class),
            minPrice: $price?->min_price === null ? null : (int) $price->min_price,
            maxPrice: $price?->max_price === null ? null : (int) $price->max_price,
            currency: Product::DEFAULT_CURRENCY,
        );
    }

    /**
     * List a lookup table's rows with the number of active products related through `products()`.
     *
     * @param  class-string<Platform|Region|ProductType|Genre|OperatingSystem>  $model
     * @return list<FilterOption>
     */
    private function optionsWithProductCounts(string $model, bool $featured = false): array
    {
        return $model::query()
            ->ordered()
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->get()
            ->map(fn (Model $option) => new FilterOption(
                value: $option->slug,
                label: $option->name,
                count: $option->products_count,
                icon: $featured ? $option->icon : null,
                isFeatured: $featured && $option->is_featured,
            ))
            ->all();
    }

    /**
     * List every country with the number of active products whose region includes it.
     *
     * @return list<FilterOption>
     */
    private function countryOptions(): array
    {
        $counts = Product::query()->active()
            ->join('country_region', 'country_region.region_id', '=', 'products.region_id')
            ->selectRaw('country_region.country_id, COUNT(*) as aggregate')
            ->groupBy('country_region.country_id')
            ->toBase()
            ->pluck('aggregate', 'country_id');

        return Country::query()
            ->ordered()
            ->get()
            ->map(fn (Country $country) => new FilterOption(
                value: $country->slug,
                label: $country->name,
                count: (int) ($counts[$country->id] ?? 0),
            ))
            ->all();
    }
}
