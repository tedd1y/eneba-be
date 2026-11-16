<?php

namespace App\Models;

use Database\Factories\HomeGalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'label',
    'href',
    'limit',
    'sort_order',
    'is_active',
])]
class HomeGallery extends Model
{
    /** @use HasFactory<HomeGalleryFactory> */
    use HasFactory;

    public const DEFAULT_LIMIT = 10;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'limit' => self::DEFAULT_LIMIT,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active galleries.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Order the query for display: by sort order, then by creation order.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }
}
