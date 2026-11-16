<?php

namespace App\Actions;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Collection;

class ListGenres
{
    /**
     * Every genre in display order. Not paginated: there are only a few dozen at most.
     *
     * @return Collection<int, Genre>
     */
    public function handle(): Collection
    {
        return Genre::query()->ordered()->get();
    }
}
