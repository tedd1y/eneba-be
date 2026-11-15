<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\ProductType;
use App\Enums\Region;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'platform',
    'region',
    'type',
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
            'platform' => Platform::class,
            'region' => Region::class,
            'type' => ProductType::class,
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
        $query->where('is_active', true);
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

        $query->whereLike('title', "%{$term}%");
    }

    /**
     * Scope a query to any of the given platforms.
     *
     * @param  array<int, Platform|string>|Platform|string|null  $platforms
     */
    #[Scope]
    protected function forPlatforms(Builder $query, array|Platform|string|null $platforms): void
    {
        $this->whereEnumIn($query, 'platform', $platforms);
    }

    /**
     * Scope a query to any of the given regions.
     *
     * @param  array<int, Region|string>|Region|string|null  $regions
     */
    #[Scope]
    protected function forRegions(Builder $query, array|Region|string|null $regions): void
    {
        $this->whereEnumIn($query, 'region', $regions);
    }

    /**
     * Scope a query to any of the given product types.
     *
     * @param  array<int, ProductType|string>|ProductType|string|null  $types
     */
    #[Scope]
    protected function forTypes(Builder $query, array|ProductType|string|null $types): void
    {
        $this->whereEnumIn($query, 'type', $types);
    }

    /**
     * Scope a query to products priced within the given range, in cents (inclusive).
     */
    #[Scope]
    protected function priceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query
            ->when($min !== null, fn (Builder $query) => $query->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('price', '<=', $max));
    }

    /**
     * Constrain an enum column to a set of values, ignoring empty input.
     *
     * @param  array<int, \BackedEnum|string>|\BackedEnum|string|null  $values
     */
    private function whereEnumIn(Builder $query, string $column, array|\BackedEnum|string|null $values): void
    {
        $values = collect(is_array($values) ? $values : [$values])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value)
            ->unique()
            ->values();

        if ($values->isNotEmpty()) {
            $query->whereIn($column, $values->all());
        }
    }
}
