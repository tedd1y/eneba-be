<?php

namespace App\Actions;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListGenres
{
    /**
     * Every genre in display order, with the number of active products in each (as `products_count`).
     * Not paginated: there are only a few dozen at most.
     *
     * @return Collection<int, Genre>
     */
    public function handle(): Collection
    {
        return Genre::query()
            ->ordered()
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->get();
    }
}
