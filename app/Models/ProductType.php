<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\ProductTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'icon', 'is_featured', 'sort_order'])]
class ProductType extends Model
{
    /** @use HasFactory<ProductTypeFactory> */
    use HasFactory, HasSortOrder;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Products using this product type.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'type_id');
    }
}
