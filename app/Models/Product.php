<?php

namespace App\Models;

use App\Enums\ProductSort;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'platform_id',
    'region_id',
    'type_id',
    'price',
    'original_price',
    'cashback',
    'currency',
    'image_url',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'original_price' => 'integer',
            'cashback' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Relations every product representation needs; eager load these whenever products are listed.
     *
     * @return list<string>
     */
    public static function summaryRelations(): array
    {
        return ['platform', 'region', 'type'];
    }

    /**
     * Relations the product detail representation needs, with many-to-many lists in display order.
     *
     * @return array<int|string, string|\Closure>
     */
    public static function detailRelations(): array
    {
        return [
            ...self::summaryRelations(),
            'genres' => fn (BelongsToMany $genres) => $genres->ordered(),
            'operatingSystems' => fn (BelongsToMany $operatingSystems) => $operatingSystems->ordered(),
        ];
    }

    /**
     * Platform the key activates on.
     *
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Region the key can be activated in.
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Kind of product.
     *
     * @return BelongsTo<ProductType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'type_id');
    }

    /**
     * Genres the product belongs to.
     *
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * Operating systems the product runs on.
     *
     * @return BelongsToMany<OperatingSystem, $this>
     */
    public function operatingSystems(): BelongsToMany
    {
        return $this->belongsToMany(OperatingSystem::class);
    }

    /**
     * Discount relative to the original price, as a whole percentage.
     */
    public function discountPercent(): int
    {
        if ($this->original_price === null || $this->original_price <= $this->price) {
            return 0;
        }

        return (int) round(($this->original_price - $this->price) / $this->original_price * 100);
    }

    /**
     * Scope a query to only include active products.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Scope a query to products whose title contains the given term.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->whereLike($this->qualifyColumn('title'), "%{$term}%");
    }

    /**
     * Scope a query to the given product IDs, ignoring empty input.
     *
     * @param  array<int, int|string>|null  $ids
     */
    #[Scope]
    protected function forIds(Builder $query, ?array $ids): void
    {
        if (! empty($ids)) {
            $query->whereKey(array_map('intval', $ids));
        }
    }

    /**
     * Scope a query to any of the given platform slugs.
     *
     * @param  list<string>|string|null  $slugs
     */
    #[Scope]
    protected function forPlatforms(Builder $query, array|string|null $slugs): void
    {
        $this->whereRelatedSlugIn($query, 'platform', $slugs);
    }

    /**
     * Scope a query to any of the given region slugs.
     *
     * @param  list<string>|string|null  $slugs
     */
    #[Scope]
    protected function forRegions(Builder $query, array|string|null $slugs): void
    {
        $this->whereRelatedSlugIn($query, 'region', $slugs);
    }

    /**
     * Scope a query to products whose region includes the given country slug.
     */
    #[Scope]
    protected function forCountry(Builder $query, ?string $slug): void
    {
        $this->whereRelatedSlugIn($query, 'region.countries', $slug);
    }

    /**
     * Scope a query to any of the given product type slugs.
     *
     * @param  list<string>|string|null  $slugs
     */
    #[Scope]
    protected function forTypes(Builder $query, array|string|null $slugs): void
    {
        $this->whereRelatedSlugIn($query, 'type', $slugs);
    }

    /**
     * Scope a query to products with any of the given genre slugs.
     *
     * @param  list<string>|string|null  $slugs
     */
    #[Scope]
    protected function forGenres(Builder $query, array|string|null $slugs): void
    {
        $this->whereRelatedSlugIn($query, 'genres', $slugs);
    }

    /**
     * Scope a query to products supporting any of the given operating system slugs.
     *
     * @param  list<string>|string|null  $slugs
     */
    #[Scope]
    protected function forOperatingSystems(Builder $query, array|string|null $slugs): void
    {
        $this->whereRelatedSlugIn($query, 'operatingSystems', $slugs);
    }

    /**
     * Scope a query to products priced within the given range, in cents (inclusive).
     */
    #[Scope]
    protected function priceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query
            ->when($min !== null, fn (Builder $query) => $query->where($this->qualifyColumn('price'), '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where($this->qualifyColumn('price'), '<=', $max));
    }

    /**
     * Order the query by the given sort, using the id as a stable tie-breaker.
     */
    #[Scope]
    protected function sorted(Builder $query, ProductSort $sort): void
    {
        $query
            ->orderBy($this->qualifyColumn($sort->column()), $sort->direction())
            ->orderBy($this->qualifyColumn('id'), $sort->direction());
    }

    /**
     * Constrain the query to products whose related record has any of the given slugs, ignoring empty input.
     *
     * @param  list<string>|string|null  $slugs
     */
    private function whereRelatedSlugIn(Builder $query, string $relation, array|string|null $slugs): void
    {
        $slugs = array_values(array_unique(array_filter(
            is_array($slugs) ? $slugs : [$slugs],
            fn (?string $slug) => $slug !== null && $slug !== '',
        )));

        if ($slugs !== []) {
            $query->whereHas($relation, fn (Builder $related) => $related->whereIn('slug', $slugs));
        }
    }
}
