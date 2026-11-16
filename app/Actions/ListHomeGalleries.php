<?php

namespace App\Actions;

use App\Models\HomeGallery;
use Illuminate\Database\Eloquent\Collection;

class ListHomeGalleries
{
    /**
     * Active home galleries in display order. Not paginated: the home page shows a handful of galleries in full.
     *
     * @return Collection<int, HomeGallery>
     */
    public function handle(): Collection
    {
        return HomeGallery::query()->active()->ordered()->get();
    }
}
