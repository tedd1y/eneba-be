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
     * Scope a query to the given platform.
     */
    #[Scope]
    protected function forPlatform(Builder $query, Platform|string|null $platform): void
    {
        if ($platform !== null) {
            $query->where('platform', $platform instanceof Platform ? $platform->value : $platform);
        }
    }

    /**
     * Scope a query to the given region.
     */
    #[Scope]
    protected function forRegion(Builder $query, Region|string|null $region): void
    {
        if ($region !== null) {
            $query->where('region', $region instanceof Region ? $region->value : $region);
        }
    }
}
