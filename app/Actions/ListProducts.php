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
     *     ids?: list<int|string>|null,
     *     search?: string|null,
     *     platform?: list<string>|null,
     *     region?: list<string>|null,
     *     country?: string|null,
     *     type?: list<string>|null,
     *     genre?: list<string>|null,
     *     os?: list<string>|null,
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
            ->with(Product::summaryRelations())
            ->active()
            ->forIds($filters['ids'] ?? null)
            ->search($filters['search'] ?? null)
            ->forPlatforms($filters['platform'] ?? null)
            ->forRegions($filters['region'] ?? null)
            ->forCountry($filters['country'] ?? null)
            ->forTypes($filters['type'] ?? null)
            ->forGenres($filters['genre'] ?? null)
            ->forOperatingSystems($filters['os'] ?? null)
            ->priceBetween(
                isset($filters['min_price']) ? (int) $filters['min_price'] : null,
                isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            )
            ->sorted(ProductSort::tryFrom($filters['sort'] ?? '') ?? self::DEFAULT_SORT)
            ->paginate((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();
    }
}
