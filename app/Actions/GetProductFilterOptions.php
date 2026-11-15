<?php

namespace App\Actions;

use App\Data\FilterOption;
use App\Data\ProductFilterOptions;
use App\Enums\Platform;
use App\Enums\ProductType;
use App\Enums\Region;
use App\Models\Product;

class GetProductFilterOptions
{
    /**
     * Collect every filter option with the number of active products matching it,
     * plus the price range of active products.
     */
    public function handle(): ProductFilterOptions
    {
        $price = Product::query()->active()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->toBase()
            ->first();

        return new ProductFilterOptions(
            platforms: $this->options('platform', Platform::cases()),
            regions: $this->options('region', Region::cases()),
            types: $this->options('type', ProductType::cases()),
            minPrice: $price?->min_price === null ? null : (int) $price->min_price,
            maxPrice: $price?->max_price === null ? null : (int) $price->max_price,
            currency: Product::DEFAULT_CURRENCY,
        );
    }

    /**
     * Pair every enum case with its active product count, in enum order.
     *
     * @param  list<Platform|Region|ProductType>  $cases
     * @return list<FilterOption>
     */
    private function options(string $column, array $cases): array
    {
        $counts = Product::query()->active()
            ->selectRaw("{$column}, COUNT(*) as aggregate")
            ->groupBy($column)
            ->toBase()
            ->pluck('aggregate', $column);

        return array_map(fn (Platform|Region|ProductType $case) => new FilterOption(
            value: $case->value,
            label: $case->label(),
            count: (int) ($counts[$case->value] ?? 0),
        ), $cases);
    }
}
