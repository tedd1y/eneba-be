<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProducts
{
    public const DEFAULT_PER_PAGE = 20;

    /**
     * List active products, filtered and paginated.
     *
     * @param  array{search?: string|null, platform?: string|null, region?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->search($filters['search'] ?? null)
            ->forPlatform($filters['platform'] ?? null)
            ->forRegion($filters['region'] ?? null)
            ->orderBy('title')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();
    }
}
