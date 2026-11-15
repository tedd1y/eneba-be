<?php

namespace App\Actions;

use App\Enums\ProductSort;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProducts
{
    public const DEFAULT_PER_PAGE = 20;

    public const DEFAULT_SORT = ProductSort::TitleAsc;

    /**
     * List active products, filtered and paginated.
     *
     * @param  array{
     *     search?: string|null,
     *     platform?: list<string>|null,
     *     region?: list<string>|null,
     *     type?: list<string>|null,
     *     min_price?: int|string|null,
     *     max_price?: int|string|null,
     *     sort?: string|null,
     *     per_page?: int|string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->search($filters['search'] ?? null)
            ->forPlatforms($filters['platform'] ?? null)
            ->forRegions($filters['region'] ?? null)
            ->forTypes($filters['type'] ?? null)
            ->priceBetween(
                isset($filters['min_price']) ? (int) $filters['min_price'] : null,
                isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            )
            ->sortBy(ProductSort::tryFrom($filters['sort'] ?? '') ?? self::DEFAULT_SORT)
            ->paginate((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();
    }
}
