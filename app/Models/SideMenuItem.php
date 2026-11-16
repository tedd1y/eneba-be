<?php

namespace App\Models;

use Database\Factories\SideMenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'label',
    'href',
    'sort_order',
    'is_active',
])]
class SideMenuItem extends Model
{
    /** @use HasFactory<SideMenuItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active items.
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
